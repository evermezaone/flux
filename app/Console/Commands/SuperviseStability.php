<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\StabilityRecovery;
use Illuminate\Console\Command;

/**
 * FLX-0048: corre el supervisor de recuperacion por estabilidad sobre cada equipo. Programado everyMinute.
 * Las autoacciones son opt-in por equipo (stability_recovery_enabled); sin opt-in solo evalua/visualiza.
 */
class SuperviseStability extends Command
{
    protected $signature = 'stability:supervise';

    protected $description = 'Escala recuperacion (diagnostico/restart/reboot) ante inestabilidad critica.';

    public function handle(StabilityRecovery $svc): int
    {
        $acted = 0;
        foreach (Device::with(['stabilityState', 'health'])->get() as $device) {
            if (! $device->stabilityState) {
                continue;
            }
            $before = $device->stabilityState->recovery_step;
            $st = $svc->tick($device);
            if ($st && $st->recovery_step !== $before) {
                $acted++;
                $this->line("{$device->code}: {$before} -> {$st->recovery_step} ({$st->last_recovery_action})");
            }
        }
        $this->info("stability:supervise -> {$acted} transicion(es).");

        return self::SUCCESS;
    }
}
