<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retencion de telemetry: purga diaria de datos crudos viejos (REQ-0010).
Schedule::command('telemetry:purge')->dailyAt('03:00');

// Alertas de salud de equipos (REQ-0026): caidos/fail -> email; recuperacion -> email. Cada 5 min.
Schedule::command('health:check-alerts')->everyFiveMinutes();

// Reinicio preventivo programado (REQ-0027): solo si esta habilitado por config, a la hora elegida.
if (config('health.restart.daily_enabled')) {
    Schedule::command('devices:restart-scheduled')
        ->dailyAt(sprintf('%02d:00', (int) config('health.restart.daily_hour', 4)));
}
