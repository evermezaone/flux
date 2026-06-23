<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\RemoteSupervisor;
use Illuminate\Console\Command;

/**
 * FLX-0041: corre el supervisor remoto sobre los equipos activos. Programado en routes/console.php.
 */
class SuperviseDevices extends Command
{
    protected $signature = 'devices:supervise';

    protected $description = 'Supervisor remoto: detecta ausencia de heartbeat y escala acciones (FLX-0041).';

    public function handle(RemoteSupervisor $supervisor): int
    {
        $attention = 0;
        foreach (Device::where('active', true)->get() as $device) {
            $sup = $supervisor->tick($device);
            if (in_array($sup->state, ['recuperando', 'requiere_intervencion', 'sin_metricas'], true)) {
                $attention++;
                $this->warn("{$device->code}: {$sup->state} — {$sup->reason}");
            }
        }
        $this->info("devices:supervise -> {$attention} equipo(s) en atencion.");

        return self::SUCCESS;
    }
}
