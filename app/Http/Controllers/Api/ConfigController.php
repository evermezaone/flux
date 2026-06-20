<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceSetting;
use App\Models\GlobalSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Config efectiva del equipo (FLX REQ-0020): global + overrides del device (device pisa global).
 * Protegido por X-Device-Key (la consume el equipo segun su id).
 */
class ConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $global = GlobalSetting::pluck('value', 'key');                 // [key => value]
        $perDevice = DeviceSetting::where('device_id', $device->id)
            ->pluck('value', 'key');

        $effective = $global->merge($perDevice); // los del equipo pisan a los globales

        return response()->json([
            'ok' => true,
            'device' => $device->code,
            'config' => $effective,
        ]);
    }
}
