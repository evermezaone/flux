<?php

/*
| FLX-0043: umbrales de energia y mantenimiento preventivo. Defaults; se pisan por equipo en
| device_settings (mismas claves) o global en global_settings.
*/
return [
    // Umbrales de bateria (%).
    'battery_warning_pct' => (int) env('ENERGY_BATTERY_WARNING', 50),
    'battery_critical_pct' => (int) env('ENERGY_BATTERY_CRITICAL', 30),
    'battery_shutdown_pct' => (int) env('ENERGY_BATTERY_SHUTDOWN', 15),
    // Temperatura maxima (C) si el equipo la reporta (0 = sin limite).
    'temp_max_c' => (int) env('ENERGY_TEMP_MAX_C', 0),
    // Mantenimiento: revision sugerida a X meses, reemplazo sugerido a Y meses.
    'review_months' => (int) env('MAINT_REVIEW_MONTHS', 12),
    'replace_months' => (int) env('MAINT_REPLACE_MONTHS', 36),
];
