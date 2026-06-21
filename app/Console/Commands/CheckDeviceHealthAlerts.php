<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Notifications\DeviceHealthAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Alertas de salud de equipos (FLX REQ-0026). Detecta equipos caidos (sin heartbeat > umbral) o en
 * `fail` y avisa por email; avisa tambien la recuperacion. Anti-spam: alerta solo al CAMBIAR de
 * estado (campo `alerted` en device_health).
 *
 * Programado en routes/console.php. Destinatarios en config('health.alert_email').
 */
class CheckDeviceHealthAlerts extends Command
{
    protected $signature = 'health:check-alerts';

    protected $description = 'Avisa por email cuando un equipo cae (offline/fail) o se recupera.';

    public function handle(): int
    {
        $offline = (int) config('health.offline_minutes', 5);
        $recipients = array_filter(array_map('trim', explode(',', (string) config('health.alert_email', ''))));

        $sent = 0;
        foreach (Device::with('health')->get() as $device) {
            $health = $device->health;
            if (! $health) {
                continue; // nunca reporto; no alertamos hasta tener una linea base
            }

            $status = $health->effectiveStatus($offline);
            $inAlert = in_array($status, ['fail', 'offline'], true);

            if ($inAlert && ! $health->alerted) {
                $this->notify($recipients, $device, $status, recovered: false);
                $health->forceFill(['alerted' => true])->save();
                $sent++;
                $this->warn("ALERTA {$device->code}: {$status}");
            } elseif (! $inAlert && $health->alerted) {
                $this->notify($recipients, $device, $status, recovered: true);
                $health->forceFill(['alerted' => false])->save();
                $sent++;
                $this->info("RECUPERADO {$device->code}: {$status}");
            }
        }

        $this->info("health:check-alerts -> {$sent} aviso(s) enviado(s).");

        return self::SUCCESS;
    }

    /** @param array<int, string> $recipients */
    private function notify(array $recipients, Device $device, string $status, bool $recovered): void
    {
        if (empty($recipients)) {
            return; // sin destinatarios configurados: no enviar (pero igual marca el estado)
        }
        Notification::route('mail', $recipients)->notify(new DeviceHealthAlert($device, $status, $recovered));
    }
}
