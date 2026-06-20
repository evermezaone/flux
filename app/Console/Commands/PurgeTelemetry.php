<?php

namespace App\Console\Commands;

use App\Models\Telemetry;
use Illuminate\Console\Command;

/**
 * Purga telemetry cruda mas vieja que N dias (retencion). REQ-0010.
 * Default N = TELEMETRY_RETENTION_DAYS (90). Solo borra telemetry cruda; no toca sites/devices/media.
 */
class PurgeTelemetry extends Command
{
    protected $signature = 'telemetry:purge {--days= : Dias de retencion (default TELEMETRY_RETENTION_DAYS o 90)}';

    protected $description = 'Borra telemetry cruda anterior al periodo de retencion.';

    public function handle(): int
    {
        // Valor desde config (config:cache-safe), no env() directo en runtime.
        $days = (int) ($this->option('days') ?: config('telemetry.retention_days', 90));
        $days = max(1, $days);
        $cutoff = now()->subDays($days);

        $deleted = Telemetry::where('ts', '<', $cutoff)->delete();

        $this->info("telemetry:purge -> {$deleted} filas borradas (anteriores a {$cutoff->toDateTimeString()} UTC, retencion {$days} dias).");

        return self::SUCCESS;
    }
}
