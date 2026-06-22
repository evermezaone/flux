<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppReleaseResolver;
use Illuminate\Http\JsonResponse;

/**
 * Manifiesto de version de la app VialSense (FLX REQ-0014).
 * Publico (sin X-Device-Key): lo consume el actualizador in-app de VLS para saber si hay
 * una version mas nueva y desde donde descargarla.
 */
class AppVersionController extends Controller
{
    public function latest(AppReleaseResolver $resolver): JsonResponse
    {
        // FLX-0037: resuelve en cadena latest.json (archivo -> HTTP -> .env). El cuerpo mantiene las
        // mismas 4 claves del contrato (la app ignora extras); el origen va en un header de diagnostico.
        $m = $resolver->resolve();

        return response()->json([
            'version_code' => (int) $m['version_code'],
            'version_name' => (string) $m['version_name'],
            'apk_url' => (string) $m['apk_url'],
            'notes' => (string) $m['notes'],
        ])->header('X-Release-Source', (string) ($m['source'] ?? 'env'));
    }

    /**
     * Version del panel/web FLX (FLX REQ-0017). Publico: sirve para verificar que version
     * del backend esta desplegada.
     */
    public function web(): JsonResponse
    {
        return response()->json([
            'app' => 'FLX',
            'web_version' => (string) config('version.web'),
        ]);
    }
}
