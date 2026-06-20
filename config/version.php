<?php

/*
| Version del panel/web FLX (FLX REQ-0017). env() solo aca (compatible con config:cache).
| Se muestra en el footer del panel y se expone en GET /api/v1/version para verificar el deploy.
| Al publicar una version nueva del backend/panel: actualizar WEB_VERSION y limpiar cache de config.
*/

return [

    // Version legible del panel web (semver).
    'web' => (string) env('WEB_VERSION', '1.0.1'),

];
