<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Site;
use App\Models\Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Estado/salud de los dispositivos (panel). */
class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Device::query()
            ->select('id', 'site_id', 'code', 'model', 'last_seen_at', 'active');

        if ($request->filled('site')) {
            $site = Site::where('code', $request->query('site'))->first();
            $query->where('site_id', $site?->id ?? 0);
        }

        $devices = $query->orderBy('code')->get();
        $deviceIds = $devices->pluck('id');

        // Ultimo snapshot de salud por dispositivo SIN N+1: 2 consultas en total,
        // respaldadas por el indice (device_id, ts). Subconsulta MAX(ts) por device + join.
        $latest = collect();
        if ($deviceIds->isNotEmpty()) {
            $maxTs = Telemetry::query()
                ->selectRaw('device_id, MAX(ts) as mts')
                ->whereIn('device_id', $deviceIds)
                ->groupBy('device_id');

            $latest = Telemetry::query()
                ->select(
                    'telemetry.device_id', 'telemetry.ts', 'telemetry.battery_pct',
                    'telemetry.temp_c', 'telemetry.cpu_pct', 'telemetry.mem_pct', 'telemetry.storage_free_pct'
                )
                ->joinSub($maxTs, 'last', function ($join) {
                    $join->on('telemetry.device_id', '=', 'last.device_id')
                        ->on('telemetry.ts', '=', 'last.mts');
                })
                ->get()
                ->keyBy('device_id');
        }

        $devices = $devices->map(function (Device $d) use ($latest) {
            $h = $latest->get($d->id);

            return [
                'id' => $d->id,
                'site_id' => $d->site_id,
                'code' => $d->code,
                'model' => $d->model,
                'active' => $d->active,
                'last_seen_at' => $d->last_seen_at,
                'health' => $h ? [
                    'ts' => $h->ts,
                    'battery_pct' => $h->battery_pct,
                    'temp_c' => $h->temp_c,
                    'cpu_pct' => $h->cpu_pct,
                    'mem_pct' => $h->mem_pct,
                    'storage_free_pct' => $h->storage_free_pct,
                ] : null,
            ];
        });

        return response()->json(['ok' => true, 'devices' => $devices]);
    }
}
