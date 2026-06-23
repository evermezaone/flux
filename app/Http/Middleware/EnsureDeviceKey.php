<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            Log::channel('telemetry')->warning('AUTH 401: sin X-Device-Key', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Falta el header X-Device-Key',
            ], 401);
        }

        $device = Device::where('device_key', $key)->where('active', true)->first();

        if (! $device) {
            // Distinguir "key no existe" de "dispositivo inactivo" para diagnostico (key enmascarada).
            $exists = Device::where('device_key', $key)->exists();
            Log::channel('telemetry')->warning('AUTH 401: X-Device-Key rechazada', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'key_prefix' => substr($key, 0, 6).'…',
                'motivo' => $exists ? 'dispositivo inactivo' : 'key inexistente',
            ]);

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
