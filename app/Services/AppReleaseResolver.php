<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resuelve el manifiesto OTA de VialSense (FLX-0037) en cadena, para poder publicar una version nueva
 * SIN tocar el .env del backend:
 *   1) archivo local (APP_RELEASE_MANIFEST_PATH) si existe -> util si vls/ es hermano en el hosting;
 *   2) HTTP a APP_RELEASE_MANIFEST_URL (def one.com.py/vls/latest.json), cacheado 60s;
 *   3) fallback a los valores de .env (config app_release.version_code/name/apk_url/notes) -> sin regresion.
 *
 * El formato de latest.json lo emite el build de VLS (VLS-0049):
 *   {"version_code":int,"version_name":string,"apk_url":string,"notes":string}
 */
class AppReleaseResolver
{
    public const CACHE_KEY = 'app_release_manifest_url';
    public const CACHE_TTL = 60; // segundos

    /**
     * @return array{version_code:int,version_name:string,apk_url:string,notes:string,source:string}
     */
    public function resolve(): array
    {
        // 1) Archivo local (si esta configurado y existe).
        $path = (string) config('app_release.manifest_path');
        if ($path !== '' && is_file($path)) {
            $data = $this->parse(@file_get_contents($path) ?: null);
            if ($data !== null) {
                $data['source'] = 'file';

                return $data;
            }
        }

        // 2) HTTP (cacheado). Cachea SIEMPRE un array (con raw=null ante fallo) para no martillar la red.
        $url = (string) config('app_release.manifest_url');
        if ($url !== '') {
            $cached = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($url) {
                try {
                    $resp = Http::timeout(5)->acceptJson()->get($url);
                    if ($resp->successful()) {
                        return ['raw' => $resp->body()];
                    }
                } catch (\Throwable $e) {
                    // Inalcanzable / timeout: cae al fallback de .env.
                }

                return ['raw' => null];
            });

            $data = $this->parse($cached['raw'] ?? null);
            if ($data !== null) {
                $data['source'] = 'url';

                return $data;
            }
        }

        // 3) Fallback a los valores de .env (comportamiento previo: sin regresion).
        return [
            'version_code' => (int) config('app_release.version_code'),
            'version_name' => (string) config('app_release.version_name'),
            'apk_url' => (string) config('app_release.apk_url'),
            'notes' => (string) config('app_release.notes'),
            'source' => 'env',
        ];
    }

    /**
     * Valida y normaliza un latest.json. Mismo criterio minimo que la app (UpdateManager.parse):
     * version_code > 0 y apk_url no vacio; si no, lo trata como invalido (-> siguiente fuente).
     *
     * @return array{version_code:int,version_name:string,apk_url:string,notes:string}|null
     */
    private function parse(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $d = json_decode($raw, true);
        if (!is_array($d)) {
            return null;
        }

        $code = (int) ($d['version_code'] ?? 0);
        $apkUrl = (string) ($d['apk_url'] ?? '');
        if ($code <= 0 || $apkUrl === '') {
            return null;
        }

        return [
            'version_code' => $code,
            'version_name' => (string) ($d['version_name'] ?? ''),
            'apk_url' => $apkUrl,
            'notes' => (string) ($d['notes'] ?? ''),
        ];
    }
}
