<?php

namespace App\Http\Middleware;

use App\Services\Firebase\AppCheckVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endurece la comunicacion FLX<->VLS verificando el token de Firebase App Check (FLX REQ-0029).
 * Va ENCIMA de X-Device-Key (no lo reemplaza). Rollout gradual via config('firebase.appcheck_mode'):
 *   off -> no verifica; warn -> verifica y loguea sin bloquear; enforce -> 401 si falta/invalido.
 *
 * Header esperado: X-Firebase-AppCheck.
 */
class VerifyFirebaseAppCheck
{
    public function __construct(private AppCheckVerifier $verifier)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('firebase.appcheck_mode', 'warn');
        if ($mode === 'off') {
            return $next($request);
        }

        $token = $request->header('X-Firebase-AppCheck');
        $valid = $this->verifier->verify($token);

        if (! $valid) {
            /** @var \App\Models\Device|null $device */
            $device = $request->attributes->get('device');
            $who = $device?->code ?? 'desconocido';

            if ($mode === 'enforce') {
                return response()->json(['ok' => false, 'error' => 'App Check invalido'], 401);
            }

            // warn: registrar y dejar pasar (migracion sin cortar equipos).
            Log::warning('AppCheck warn: token ausente/invalido', ['device' => $who, 'path' => $request->path()]);
        }

        return $next($request);
    }
}
