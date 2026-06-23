<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\DeviceSupervision;
use App\Models\GlobalSetting;
use App\Services\Fcm\FcmSender;
use Illuminate\Support\Carbon;

/**
 * FLX-0041: supervisor remoto industrial. Por cada equipo evalua la antiguedad del heartbeat
 * (`devices.last_seen_at`) y, ante ausencia de metricas, escala acciones con anti-tormenta:
 *
 *   online -> degradado -> sin_metricas -> (escalado) recuperando -> requiere_intervencion
 *
 * Escalado (un paso por cooldown, tope por ventana): get_logs (FCM) -> ping (FCM) -> restart app ->
 * restart device (si esta permitido) -> requiere_intervencion. Todo queda registrado en
 * `device_supervisions` (estado, paso, ultima accion, canal, motivo, hora) -> visible en el panel.
 *
 * Autoacciones OPT-IN (apagadas por defecto). Config por equipo en device_settings (pisa global_settings,
 * pisa config('supervisor.*')).
 */
class RemoteSupervisor
{
    public function __construct(private CommandDispatcher $dispatcher, private FcmSender $fcm) {}

    public function tick(Device $device): DeviceSupervision
    {
        $sup = DeviceSupervision::firstOrNew(
            ['device_id' => $device->id],
            ['state' => 'online', 'step' => 0, 'window_count' => 0],
        );
        $cfg = $this->config($device);

        $lastSeen = $device->last_seen_at;
        $secs = $lastSeen ? Carbon::now()->diffInSeconds($lastSeen, absolute: true) : PHP_INT_MAX;

        // Online: heartbeat fresco -> reset del escalamiento.
        if ($secs <= $cfg['heartbeat_interval_s']) {
            if ($sup->state !== 'online') {
                $sup->reason = 'heartbeat recuperado';
            }
            $sup->state = 'online';
            $sup->step = 0;
            $sup->save();

            return $sup;
        }

        // Degradado: atrasado pero dentro de tolerancia -> observar, no actuar.
        if ($secs <= $cfg['heartbeat_interval_s'] + $cfg['offline_tolerance_s']) {
            $sup->state = 'degradado';
            $sup->reason = "heartbeat atrasado ({$secs}s)";
            $sup->save();

            return $sup;
        }

        // Sin metricas. Si las autoacciones estan apagadas, solo reportar.
        if (! $cfg['enabled']) {
            $sup->state = 'sin_metricas';
            $sup->reason = "sin heartbeat ({$secs}s); autoacciones deshabilitadas";
            $sup->save();

            return $sup;
        }

        // Ventana anti-tormenta: reiniciar el contador si la ventana expiro.
        if (! $sup->window_started_at || Carbon::now()->diffInSeconds($sup->window_started_at, absolute: true) > $cfg['window_s']) {
            $sup->window_started_at = Carbon::now();
            $sup->window_count = 0;
        }
        if ($sup->window_count >= $cfg['max_actions_per_window']) {
            $sup->state = 'requiere_intervencion';
            $sup->reason = "tope de {$cfg['max_actions_per_window']} acciones/ventana sin recuperacion";
            $sup->save();

            return $sup;
        }

        // Cooldown entre pasos: no disparar todos los pasos de golpe (anti-tormenta).
        if ($sup->last_action_at && Carbon::now()->diffInSeconds($sup->last_action_at, absolute: true) < $cfg['action_cooldown_s']) {
            $sup->state = 'recuperando';
            $sup->save();

            return $sup;
        }

        $this->escalate($device, $sup, $cfg);
        $sup->save();

        return $sup;
    }

    private function escalate(Device $device, DeviceSupervision $sup, array $cfg): void
    {
        $mark = function (string $name, string $channel, string $reason) use ($sup): void {
            $sup->last_action = $name;
            $sup->last_action_channel = $channel;
            $sup->last_action_at = Carbon::now();
            $sup->reason = $reason;
            $sup->window_count += 1;
            $sup->state = 'recuperando';
        };

        switch ($sup->step) {
            case 0:
                $this->dispatcher->dispatch($device, 'get_logs', [], 'fcm');
                $mark('get_logs', 'fcm', 'sin metricas -> diagnostico (get_logs)');
                $sup->step = 1;
                break;
            case 1:
                $this->ping($device);
                $mark('ping', 'fcm', 'sin recuperacion -> ping');
                $sup->step = 2;
                break;
            case 2:
                $this->dispatcher->dispatch($device, 'restart', ['level' => 'app'], 'fcm');
                $mark('restart_app', 'fcm', 'sin recuperacion -> reinicio app');
                $sup->step = 3;
                break;
            case 3:
                if ($cfg['allow_device_reboot']) {
                    // restart device se fuerza a fcm en CommandDispatcher (FLX-0040).
                    $this->dispatcher->dispatch($device, 'restart', ['level' => 'device'], 'fcm');
                    $mark('restart_device', 'fcm', 'sin recuperacion -> reinicio equipo');
                    $sup->step = 4;
                } else {
                    $sup->state = 'requiere_intervencion';
                    $sup->reason = 'sin recuperacion; reinicio de equipo no permitido por config';
                    $sup->step = 4;
                }
                break;
            default:
                $sup->state = 'requiere_intervencion';
                $sup->reason = 'escalamiento agotado; requiere intervencion';
                break;
        }
    }

    private function ping(Device $device): void
    {
        if (blank($device->fcm_token)) {
            return;
        }
        try {
            $this->fcm->send($device->fcm_token, ['action' => 'ping']);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /** @return array<string,mixed> */
    private function config(Device $device): array
    {
        return [
            'enabled' => $this->boolSetting($device, 'supervise_enabled', config('supervisor.enabled', false)),
            'heartbeat_interval_s' => (int) $this->setting($device, 'heartbeat_interval_s', config('supervisor.heartbeat_interval_s', 120)),
            'offline_tolerance_s' => (int) $this->setting($device, 'offline_tolerance_s', config('supervisor.offline_tolerance_s', 180)),
            'max_actions_per_window' => (int) $this->setting($device, 'supervise_max_actions', config('supervisor.max_actions_per_window', 4)),
            'window_s' => (int) $this->setting($device, 'supervise_window_s', config('supervisor.window_s', 3600)),
            'action_cooldown_s' => (int) $this->setting($device, 'supervise_cooldown_s', config('supervisor.action_cooldown_s', 120)),
            'allow_device_reboot' => $this->boolSetting($device, 'supervise_allow_device_reboot', config('supervisor.allow_device_reboot', false)),
        ];
    }

    private function setting(Device $device, string $key, $default)
    {
        $v = DeviceSetting::where('device_id', $device->id)->where('key', $key)->value('value')
            ?? GlobalSetting::where('key', $key)->value('value');

        return $v ?? $default;
    }

    private function boolSetting(Device $device, string $key, bool $default): bool
    {
        $v = DeviceSetting::where('device_id', $device->id)->where('key', $key)->value('value')
            ?? GlobalSetting::where('key', $key)->value('value');
        if ($v === null) {
            return $default;
        }

        return in_array(strtolower((string) $v), ['1', 'true', 'si', 'yes', 'on'], true);
    }
}
