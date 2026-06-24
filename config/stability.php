<?php

/*
| FLX-0047: parametros de estabilidad (registro/alertas). recurrent_events = cuantos eventos en 24h
| elevan el estado a critical. alert_email reusa el de salud si no se define uno propio.
*/
return [
    'recurrent_events' => (int) env('STABILITY_RECURRENT_EVENTS', 3),
    'alert_email' => (string) env('STABILITY_ALERT_EMAIL', (string) env('HEALTH_ALERT_EMAIL', '')),

    // FLX-0048: recuperacion automatica por estabilidad. Opt-in por equipo (stability_recovery_enabled).
    'recovery' => [
        'enabled' => (bool) env('STABILITY_RECOVERY_ENABLED', false), // default global; se puede activar por device
        'action_cooldown_s' => (int) env('STABILITY_ACTION_COOLDOWN_S', 90),   // espera entre acciones
        'max_actions_window' => (int) env('STABILITY_MAX_ACTIONS_WINDOW', 4),  // tope de acciones por hora
    ],
];
