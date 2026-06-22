<?php

/*
| Manifiesto de la ultima version publicada de la app VialSense (VLS).
| env() solo aca (compatible con config:cache). Lo consume GET /api/v1/app/latest
| y el actualizador in-app de VLS (VLS REQ-0010).
|
| Al publicar una nueva APK: actualizar APP_VERSION_CODE / APP_VERSION_NAME / APP_APK_URL
| (en .env del server) y limpiar cache de config.
*/

return [

    // Codigo de version entero (debe coincidir con versionCode del APK publicado).
    'version_code' => (int) env('APP_VERSION_CODE', 17),

    // Nombre de version legible (versionName del APK).
    'version_name' => (string) env('APP_VERSION_NAME', '1.7'),

    // URL publica de descarga del APK (verificada: HTTP 200, application/vnd.android.package-archive).
    'apk_url' => (string) env('APP_APK_URL', 'https://one.com.py/vls/app-debug.apk'),

    // Notas de la version (changelog corto).
    'notes' => (string) env('APP_RELEASE_NOTES', 'Version estable de VialSense.'),

    /*
    | FLX-0037: fuentes del manifiesto publicado por el build de VLS (latest.json). El endpoint las
    | resuelve en cadena (archivo -> HTTP -> estos valores de .env). Con esto, publicar una version
    | nueva es subir app-debug.apk + latest.json a one.com.py/vls, SIN editar .env ni config:cache.
    */

    // Ruta local opcional al latest.json (si vls/ es accesible por filesystem desde el hosting de FLX).
    'manifest_path' => env('APP_RELEASE_MANIFEST_PATH'),

    // URL publica del latest.json (junto al APK). Default: hermano del apk_url.
    'manifest_url' => env('APP_RELEASE_MANIFEST_URL', 'https://one.com.py/vls/latest.json'),

];
