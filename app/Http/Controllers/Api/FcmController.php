<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro del token FCM del equipo (FLX REQ-0028; emisor VLS-0025). Protegido por X-Device-Key.
 * El token permite al backend enviarle push para "despertarlo".
 *
 * Contrato: POST /api/v1/fcm-token  body: { "token": "..." }
 */
class FcmController extends Controller
{
    public function registerToken(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $device->forceFill([
            'fcm_token' => $data['token'],
            'fcm_token_at' => now(),
        ])->save();

        return response()->json(['ok' => true, 'device' => $device->code]);
    }
}
