<?php

/*
| Configuracion de telemetry. env() solo aca (compatible con config:cache).
| La retencion de datos crudos se lee con config('telemetry.retention_days').
*/

return [

    // Dias de retencion de telemetry cruda antes de la purga programada.
    'retention_days' => (int) env('TELEMETRY_RETENTION_DAYS', 90),

];
