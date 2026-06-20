<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Manifiesto de version de la app VialSense (FLX REQ-0014).
 * Publico (sin X-Device-Key): lo consume el actualizador in-app de VLS para saber si hay
 * una version mas nueva y desde donde descargarla.
 */
class AppVersionController extends Controller
{
    public function latest(): JsonResponse
    {
        return response()->json([
            'version_code' => (int) config('app_release.version_code'),
            'version_name' => (string) config('app_release.version_name'),
            'apk_url' => (string) config('app_release.apk_url'),
            'notes' => (string) config('app_release.notes'),
        ]);
    }
}
