<?php

/*
| Configuracion de la ingesta. env() solo aca (compatible con config:cache).
| El rate-limit de ingesta se lee con config('ingesta.rate_limit').
*/

return [

    // Maximo de requests de ingesta por minuto, por device-key.
    'rate_limit' => (int) env('INGESTA_RATE_LIMIT', 120),

];
