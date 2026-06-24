<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceStabilityState;
use App\Models\StabilityEvent;
use Illuminate\Support\Carbon;

/**
 * FLX-0047: ingiere el bloque `device.stability` del heartbeat (lo produce VLS-0068), persiste los eventos
 * de estabilidad (idempotente por (device_id, event_id)) y recalcula el estado consolidado por equipo
 * (agregados 24h + status). Best-effort: no debe romper la ingesta del heartbeat.
 *
 * Contrato esperado:
 *   device.stability = {
 *     ui_frozen: bool, ui_last_tick_at: ISO, last_diagnostic_id: string|null,
 *     events: [ { event_id, event_type, severity, occurred_at, recovered_at, app_version,
 *                 sentinel_version, summary, details{}, diagnostic_id } ]
 *   }
 */
class StabilityIngestor
{
    public function ingest(Device $device, ?array $stability): ?DeviceStabilityState
    {
        if (! is_array($stability)) {
            return null; // el equipo aun no reporta estabilidad (VLS-0068 no desplegado)
        }

        foreach ((array) ($stability['events'] ?? []) as $e) {
            if (! is_array($e) || empty($e['event_id'])) {
                continue;
            }
            StabilityEvent::updateOrCreate(
                ['device_id' => $device->id, 'event_id' => (string) $e['event_id']],
                [
                    'event_type' => (string) ($e['event_type'] ?? 'unknown'),
                    'severity' => ($e['severity'] ?? 'warn') === 'critical' ? 'critical' : 'warn',
                    'occurred_at' => $this->ts($e['occurred_at'] ?? null),
                    'recovered_at' => $this->ts($e['recovered_at'] ?? null),
                    'app_version' => $e['app_version'] ?? null,
                    'sentinel_version' => $e['sentinel_version'] ?? null,
                    'summary' => isset($e['summary']) ? mb_substr((string) $e['summary'], 0, 255) : null,
                    'details' => is_array($e['details'] ?? null) ? $e['details'] : null,
                    'diagnostic_id' => $e['diagnostic_id'] ?? null,
                ]
            );
        }

        return $this->recompute($device, $stability);
    }

    /** Recalcula los agregados 24h + status y persiste el estado del equipo. */
    public function recompute(Device $device, array $stability): DeviceStabilityState
    {
        $since = now()->subDay();
        $base = StabilityEvent::where('device_id', $device->id)->where('occurred_at', '>=', $since);

        $crash = (clone $base)->where('event_type', 'crash')->count();
        $anr = (clone $base)->where('event_type', 'anr_suspected')->count();
        $uiFreeze = (clone $base)->where('event_type', 'ui_frozen_timeout')->count();
        // Codex R2: contrato de Application Error. Canonico = `app_error_suspected` (VLS-0068 + comentario de
        // migracion); se acepta tambien el alias `application_error_suspected` (VLS-0071) para no perder el conteo.
        $appError = (clone $base)->whereIn('event_type', ['app_error_suspected', 'application_error_suspected'])->count();
        $total = (clone $base)->count(); // FLX-0047 R1: TODO evento en 24h es accionable (no solo crash/anr/ui)

        $last = StabilityEvent::where('device_id', $device->id)->latest('occurred_at')->first();
        $uiFrozen = (bool) ($stability['ui_frozen'] ?? false);
        // FLX-0047 R1: criticos = UI congelada, crash, critico sin recuperar, escalado a reboot, o RECURRENCIA
        // (N+ eventos en la ventana). Cualquier OTRO evento accionable en 24h (app_error, activity_relaunch_loop,
        // anr, ui_freeze, etc.) deja al menos warn -> ningun evento de campo queda invisible como ok.
        $hasOpenCritical = (clone $base)->where('severity', 'critical')->whereNull('recovered_at')->exists();
        $rebootEscalated = (clone $base)->where('event_type', 'recovery_escalated_to_reboot')->exists();
        $recurrentN = (int) config('stability.recurrent_events', 3);
        $recurrent = $total >= $recurrentN;
        $status = match (true) {
            $uiFrozen || $crash > 0 || $hasOpenCritical || $rebootEscalated || $recurrent => 'critical',
            $total > 0 => 'warn',
            default => 'ok',
        };

        return DeviceStabilityState::updateOrCreate(
            ['device_id' => $device->id],
            [
                'stability_status' => $status,
                'crash_count_24h' => $crash,
                'anr_count_24h' => $anr,
                'ui_freeze_count_24h' => $uiFreeze,
                'app_error_count_24h' => $appError,
                'event_count_24h' => $total,
                'last_stability_event' => $last?->event_type,
                'last_stability_event_at' => $last?->occurred_at,
                'ui_frozen' => $uiFrozen,
                'ui_last_tick_at' => $this->ts($stability['ui_last_tick_at'] ?? null),
                'last_diagnostic_id' => $stability['last_diagnostic_id'] ?? null,
            ]
        );
    }

    private function ts(mixed $v): ?Carbon
    {
        if (empty($v) || ! is_string($v)) {
            return null;
        }

        return rescue(fn () => Carbon::parse($v)->utc(), null, false);
    }
}
