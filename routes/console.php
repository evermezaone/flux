<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retencion de telemetry: purga diaria de datos crudos viejos (REQ-0010).
Schedule::command('telemetry:purge')->dailyAt('03:00');
