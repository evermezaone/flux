<?php

/**
 * Configuracion de Firebase Admin (FLX REQ-0028/0029). Solo env() aqui (config:cache-safe).
 * La clave de service account es SECRETA: vive fuera del repo (storage/app/firebase/) y se referencia
 * por ruta; nunca se hardcodea ni se commitea.
 */
return [
    // Ruta al JSON de la cuenta de servicio (Firebase Admin SDK).
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),

    // project_id del proyecto Firebase (informativo / validaciones).
    'project_id' => env('FIREBASE_PROJECT_ID', 'flux-4f178'),

    // App Check (REQ-0029): rollout gradual del endurecimiento de la comunicacion.
    //   off     = no se verifica (comportamiento previo).
    //   warn    = se verifica y se registra, pero NO se bloquea (migracion sin cortar equipos).
    //   enforce = token ausente/invalido -> 401.
    'appcheck_mode' => env('FIREBASE_APPCHECK_MODE', 'warn'),
];
