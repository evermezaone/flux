<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Telemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Datos para el mapa del panel (FLX REQ-0025): cada cruce con su ubicacion y su telemetria mas
 * reciente. Modo "en vivo" (ultimo valor) o "historico" (ultimo valor <= ?at=ISO8601).
 * Solo operador (auth de sesion).
 */
class MapController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $at = $request->query('at');
        $atDate = $at ? \Illuminate\Support\Carbon::parse($at) : null;

        $sites = Site::query()->orderBy('code')->get();

        $located = [];
        $unlocated = [];

        foreach ($sites as $site) {
            $last = Telemetry::query()
                ->where('site_id', $site->id)
                ->when($atDate, fn ($q) => $q->where('ts', '<=', $atDate))
                ->orderByDesc('ts')
                ->first();

            $entry = [
                'id' => $site->id,
                'code' => $site->code,
                'name' => $site->name,
                'lat' => $site->lat !== null ? (float) $site->lat : null,
                'lng' => $site->lng !== null ? (float) $site->lng : null,
                'location_manual' => (bool) $site->location_manual,
                'devices' => $site->devices()->pluck('code')->all(),
                'last' => $last ? [
                    'ts' => optional($last->ts)->toIso8601String(),
                    'zone' => $last->zone,
                    'occupancy' => $last->occupancy,
                    'pressure' => $last->pressure,
                    'congestion' => $last->congestion,
                    'decision' => $last->decision,
                    'battery_pct' => $last->battery_pct,
                ] : null,
            ];

            if ($entry['lat'] !== null && $entry['lng'] !== null) {
                $located[] = $entry;
            } else {
                $unlocated[] = $entry;
            }
        }

        return response()->json([
            'ok' => true,
            'mode' => $atDate ? 'historico' : 'vivo',
            'at' => $atDate?->toIso8601String(),
            'sites' => $located,
            'unlocated' => $unlocated, // cruces sin coordenadas (no se pierden)
        ]);
    }
}
