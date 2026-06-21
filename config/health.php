<?php

/**
 * Parametros del monitor de salud de equipos (FLX REQ-0026).
 * Solo env() aqui (config:cache-safe).
 */
return [
    // Un equipo se considera offline si no manda heartbeat hace mas de N minutos.
    'offline_minutes' => (int) env('HEALTH_OFFLINE_MINUTES', 5),

    // Destinatario(s) de las alertas por email (coma-separado). Vacio = no enviar email.
    'alert_email' => env('HEALTH_ALERT_EMAIL', ''),

    // Reinicio preventivo programado desde el backend (REQ-0027): encola `restart` a todos los equipos.
    'restart' => [
        'daily_enabled' => (bool) env('RESTART_DAILY_ENABLED', false),
        'daily_hour' => (int) env('RESTART_DAILY_HOUR', 4),     // 0..23
        'level' => env('RESTART_DAILY_LEVEL', 'app'),           // service | app | device
    ],
];
