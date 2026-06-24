<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Salud de equipos (FLX REQ-0026).
 * - store(): recibe el heartbeat del VLS-0022 (X-Device-Key) y guarda el ultimo estado.
 * - healthz(): endpoint plano (200/503) para monitores externos (global o ?device=code).
 */
class HealthController extends Controller
{
    /** Recibe el heartbeat + autodiagnostico del equipo y persiste el ultimo estado. */
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'overall' => ['required', 'in:ok,warn,fail'],
            'subsystems' => ['nullable', 'array'],
            'device' => ['nullable', 'array'],
            'uptime_s' => ['nullable', 'integer', 'min:0'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'app_build' => ['nullable', 'integer', 'min:0'],
            'ts' => ['nullable', 'date'], // hora del equipo (informativa); NO se usa para offline
        ]);

        DeviceHealth::updateOrCreate(
            ['device_id' => $device->id],
            [
                'overall' => $data['overall'],
                'subsystems' => $data['subsystems'] ?? null,
                'device_metrics' => $data['device'] ?? null,
                'uptime_s' => $data['uptime_s'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'app_build' => $data['app_build'] ?? null,
                // Obs 168: reported_at = hora de RECEPCION del servidor (no el reloj del equipo, que
                // puede estar desfasado y falsear el calculo de offline / alertas).
                'reported_at' => now(),
            ]
        );

        $device->forceFill(['last_seen_at' => now()])->save();

        // FLX-0044: evaluar prerequisitos operativos (device.operational_requirements) y persistir alertas
        // con anti-tormenta (solo cambios). Best-effort: no debe romper la ingesta del heartbeat.
        try {
            app(\App\Services\RequirementsMonitor::class)->evaluate($device->fresh('health'));
        } catch (\Throwable $e) {
            // se ignora: el heartbeat ya quedo persistido
        }

        // FLX-0047: ingerir eventos de estabilidad (device.stability) y recalcular el estado. Best-effort.
        try {
            app(\App\Services\StabilityIngestor::class)->ingest($device, $data['device']['stability'] ?? null);
        } catch (\Throwable $e) {
            // se ignora: el heartbeat ya quedo persistido
        }

        // FLX-0051: evaluar fallas criticas de Sentinel/permisos y levantar alerta si corresponde. Best-effort.
        try {
            app(\App\Services\SentinelSupervisor::class)->evaluate($device->fresh('health'));
        } catch (\Throwable $e) {
            // se ignora: el heartbeat ya quedo persistido
        }

        return response()->json(['ok' => true, 'device' => $device->code, 'overall' => $data['overall']]);
    }

    /** Endpoint plano para monitores externos: 200 si todo OK/online, 503 si hay caidos o en fail. */
    public function healthz(Request $request): JsonResponse
    {
        $offline = (int) config('health.offline_minutes', 5);
        $deviceCode = $request->query('device');

        if ($deviceCode) {
            $device = Device::where('code', $deviceCode)->first();
            if (! $device) {
                return response()->json(['ok' => false, 'error' => 'device desconocido'], 404);
            }
            $status = $device->health?->effectiveStatus($offline) ?? 'offline';
            $ok = in_array($status, ['ok', 'warn'], true);

            return response()->json([
                'ok' => $ok,
                'device' => $device->code,
                'status' => $status,
            ], $ok ? 200 : 503);
        }

        // Global: contar por estado efectivo.
        $devices = Device::with('health')->get();
        $counts = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'offline' => 0];
        foreach ($devices as $d) {
            $status = $d->health?->effectiveStatus($offline) ?? 'offline';
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        $degraded = $counts['fail'] > 0 || $counts['offline'] > 0;

        return response()->json([
            'ok' => ! $degraded,
            'total' => $devices->count(),
            'counts' => $counts,
        ], $degraded ? 503 : 200);
    }
}
