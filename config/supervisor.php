<?php

/*
| FLX-0041: defaults del supervisor remoto. Se pueden pisar por equipo en device_settings (mismas claves)
| o global en global_settings. Por seguridad, las autoacciones estan APAGADAS por defecto (opt-in).
*/
return [
    'enabled' => (bool) env('SUPERVISOR_ENABLED', false),         // autoacciones (opt-in por equipo)
    'heartbeat_interval_s' => (int) env('SUPERVISOR_HEARTBEAT_S', 120),
    'offline_tolerance_s' => (int) env('SUPERVISOR_TOLERANCE_S', 180),
    'max_actions_per_window' => (int) env('SUPERVISOR_MAX_ACTIONS', 4),
    'window_s' => (int) env('SUPERVISOR_WINDOW_S', 3600),
    'action_cooldown_s' => (int) env('SUPERVISOR_COOLDOWN_S', 120), // espera entre pasos de escalamiento
    'allow_device_reboot' => (bool) env('SUPERVISOR_ALLOW_DEVICE_REBOOT', false),
];
