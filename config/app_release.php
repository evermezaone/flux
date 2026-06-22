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

];
