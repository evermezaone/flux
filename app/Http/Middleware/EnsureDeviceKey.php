<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica la ingesta por header X-Device-Key.
 * Si es valido, deja el Device disponible en request->attributes('device').
 */
class EnsureDeviceKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Device-Key');

        if (! $key) {
            return response()->json([
                'ok' => false,
                'error' => 'Falta el header X-Device-Key',
            ], 401);
        }

        $device = Device::where('device_key', $key)->where('active', true)->first();

        if (! $device) {
            return response()->json([
                'ok' => false,
                'error' => 'X-Device-Key invalido o dispositivo inactivo',
            ], 401);
        }

        // Disponible para los controladores de ingesta (REQ-0003/0004).
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
