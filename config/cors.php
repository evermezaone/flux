<?php

/*
| CORS — restringido al dominio del panel.
| Los origenes permitidos salen de CORS_ALLOWED_ORIGINS en .env (coma-separados).
| La ingesta de VialSense (app Android) no usa CORS de navegador.
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', ''))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Device-Key', 'Accept', 'Authorization', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
