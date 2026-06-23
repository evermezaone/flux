<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Ingesta de telemetria (VialSense -> FLX). Acepta 1 o N registros.
 * Idempotente por (device_id, client_seq). Append-only. Timestamps a UTC.
 */
class TelemetryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $payload = $request->json()->all();
        if (isset($payload['records']) && is_array($payload['records'])) {
            $records = $payload['records'];
        } elseif (is_array($payload) && array_is_list($payload)) {
            $records = $payload;
        } else {
            $records = [$payload];
        }

        // Traza de llegada: prueba que la telemetria entro (paso auth) y QUE llego, antes de registrar.
        $seqs = array_filter(array_map(fn ($r) => is_array($r) ? ($r['client_seq'] ?? null) : null, $records), fn ($s) => $s !== null);
        Log::channel('telemetry')->info('RECIBIDO', [
            'device' => $device->code,
            'records' => count($records),
            'client_seq' => $seqs ? (min($seqs).'..'.max($seqs)) : 'n/d',
            'bytes' => strlen($request->getContent()),
            'ip' => $request->ip(),
        ]);

        $rows = [];
        $invalid = 0;

        foreach ($records as $i => $rec) {
            if (! is_array($rec)) {
                $invalid++;
                continue;
            }
            $v = Validator::make($rec, [
                'ts' => ['required', 'date'],
                'client_seq' => ['required', 'integer', 'min:0'],
                'traffic.zone' => ['nullable', 'string', 'max:40'],
                'traffic.occupancy' => ['nullable', 'integer'],
                'traffic.queue_len_m' => ['nullable', 'numeric'],
                'traffic.pressure' => ['nullable', 'integer'],
                'traffic.congestion' => ['nullable', 'in:low,med,high,saturated'],
                'traffic.decision' => ['nullable', 'string', 'max:40'],
                'traffic.wait_est_s' => ['nullable', 'numeric'],
                'traffic.empty_s' => ['nullable', 'integer'],
                'device.battery_pct' => ['nullable', 'integer', 'between:0,100'],
                'device.temp_c' => ['nullable', 'numeric'],
                'device.cpu_pct' => ['nullable', 'integer', 'between:0,100'],
                'device.mem_pct' => ['nullable', 'integer', 'between:0,100'],
                'device.storage_free_pct' => ['nullable', 'integer', 'between:0,100'],
            ]);

            if ($v->fails()) {
                // Loguea y sigue: un registro malo no frena el lote.
                Log::channel('telemetry')->warning('REGISTRO INVALIDO (no se guarda)', [
                    'device' => $device->code,
                    'index' => $i,
                    'errors' => $v->errors()->all(),
                ]);
                $invalid++;
                continue;
            }

            $rows[] = [
                'device_id' => $device->id,
                'site_id' => $device->site_id,
                'ts' => Carbon::parse($rec['ts'])->utc()->toDateTimeString(),
                'client_seq' => (int) $rec['client_seq'],
                // FLX-0045: idempotencia por contenido. Se usa el hash que manda el telefono si viene;
                // si no, se computa server-side con los mismos campos (compat con telefonos sin actualizar).
                'client_hash' => self::recordHash($rec),
                'zone' => data_get($rec, 'traffic.zone'),
                'occupancy' => data_get($rec, 'traffic.occupancy'),
                'queue_len_m' => data_get($rec, 'traffic.queue_len_m'),
                'pressure' => data_get($rec, 'traffic.pressure'),
                'congestion' => data_get($rec, 'traffic.congestion'),
                'decision' => data_get($rec, 'traffic.decision'),
                'wait_est_s' => data_get($rec, 'traffic.wait_est_s'),
                'empty_s' => data_get($rec, 'traffic.empty_s'),
                'battery_pct' => data_get($rec, 'device.battery_pct'),
                'temp_c' => data_get($rec, 'device.temp_c'),
                'cpu_pct' => data_get($rec, 'device.cpu_pct'),
                'mem_pct' => data_get($rec, 'device.mem_pct'),
                'storage_free_pct' => data_get($rec, 'device.storage_free_pct'),
            ];
        }

        $accepted = 0;
        if ($rows) {
            // insertOrIgnore: respeta el indice unico (device_id, client_seq) -> deduplica.
            $accepted = Telemetry::insertOrIgnore($rows);
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        $result = [
            'ok' => true,
            'accepted' => $accepted,
            'duplicated' => count($rows) - $accepted,
            'invalid' => $invalid,
        ];

        // Traza de resultado: cuantos se registraron / duplicados / invalidos.
        Log::channel('telemetry')->info('RESULTADO', ['device' => $device->code] + $result);

        return response()->json($result);
    }

    /**
     * FLX-0045: clave de idempotencia por contenido. Si el registro trae `client_hash` (lo manda el
     * telefono, VLS-0065) se usa tal cual; si no, se computa SHA-256 sobre todos los campos de contenido
     * del registro de forma deterministica (claves ordenadas), excluyendo el propio client_hash.
     */
    private static function recordHash(array $rec): string
    {
        $given = $rec['client_hash'] ?? null;
        if (is_string($given) && strlen($given) >= 16) {
            return substr($given, 0, 64);
        }
        $content = $rec;
        unset($content['client_hash']);

        return hash('sha256', self::canonical($content));
    }

    /** Serializa de forma estable (recursivo, claves ordenadas) para que el hash sea reproducible. */
    private static function canonical(mixed $value): string
    {
        if (is_array($value)) {
            $assoc = ! array_is_list($value);
            if ($assoc) {
                ksort($value);
            }
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = ($assoc ? $k.':' : '').self::canonical($v);
            }

            return '['.implode(',', $parts).']';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value === null ? 'null' : (string) $value;
    }
}
