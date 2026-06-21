<?php

namespace App\Console\Commands;

use App\Models\Command as DeviceCommand;
use App\Models\Device;
use Illuminate\Console\Command;

/**
 * Reinicio preventivo programado (FLX REQ-0027): encola un comando `restart` a todos los equipos
 * con el nivel configurado. Pensado para una ventana de madrugada (programado en routes/console.php).
 */
class ScheduledRestartDevices extends Command
{
    protected $signature = 'devices:restart-scheduled {--level= : service|app|device (default config)}';

    protected $description = 'Encola un reinicio preventivo a todos los equipos (REQ-0027).';

    public function handle(): int
    {
        $level = (string) ($this->option('level') ?: config('health.restart.level', 'app'));
        if (! in_array($level, ['service', 'app', 'device'], true)) {
            $this->error("Nivel invalido: {$level}");

            return self::FAILURE;
        }

        $n = 0;
        foreach (Device::query()->where('active', true)->get() as $device) {
            $command = DeviceCommand::create([
                'device_id' => $device->id,
                'cmd' => 'restart',
                'params' => ['level' => $level],
                'status' => 'pending',
            ]);
            $command->logEvent('created');
            $n++;
        }

        $this->info("devices:restart-scheduled -> {$n} reinicio(s) encolado(s) (nivel {$level}).");

        return self::SUCCESS;
    }
}
