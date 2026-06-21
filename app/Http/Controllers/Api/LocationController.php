<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recepcion de la ubicacion GPS del equipo (FLX REQ-0025; emisor: VLS REQ-0021).
 * Protegido por X-Device-Key. Actualiza la ubicacion del CRUCE del equipo, salvo que el operador
 * la haya fijado manualmente (location_manual = true tiene precedencia sobre el GPS).
 *
 * Contrato: POST /api/v1/location  body: { lat, lng, accuracy_m?, ts?, site_id? }
 */
class LocationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'ts' => ['nullable', 'date'],
        ]);

        $site = Site::find($device->site_id);
        if (! $site) {
            return response()->json(['ok' => false, 'error' => 'device sin cruce asociado'], 422);
        }

        // El ajuste manual del operador manda: no sobreescribir con el GPS.
        if ($site->location_manual) {
            return response()->json([
                'ok' => true,
                'applied' => false,
                'reason' => 'ubicacion fijada manualmente',
            ]);
        }

        $site->forceFill([
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'location_accuracy_m' => $data['accuracy_m'] ?? null,
            'location_updated_at' => $data['ts'] ?? now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'applied' => true,
            'site' => $site->code,
        ]);
    }
}
