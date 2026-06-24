<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\DeviceStabilityState;
use App\Models\GlobalSetting;
use Illuminate\Support\Carbon;

/**
 * FLX-0048 (+ FLX-0050): supervisor de recuperacion por estabilidad. Cuando un equipo queda critical
 * (UI congelada, ANR/crash recurrente, relaunch loop, Sentinel hibernado) escala acciones de forma ordenada:
 * get_diagnostics -> restart_app -> reboot_device (solo Device Owner). Reglas especiales (FLX-0050):
 *  - activity_relaunch_loop: NO mandar restart_app (realimenta el loop); pedir diagnostico y HOLD.
 *  - sentinel_oem_hibernation_suspected: actuar desde central (no asumir watchdog sano); pedir diagnostico.
 *
 * Seguridad: no actua durante update_in_progress; cooldown por accion; tope de acciones por ventana;
 * opt-in por equipo (stability_recovery_enabled). Registra cada accion (last_recovery_action + command id).
 */
class StabilityRecovery
{
    public function __construct(private CommandDispatcher $dispatcher) {}

    public function tick(Device $device): ?DeviceStabilityState
    {
        $st = $device->stabilityState;
        if (! $st) {
            return null;
        }

        // Recuperado: volver a idle.
        if ($st->stability_status !== 'critical') {
            if ($st->recovery_step !== 'idle') {
                $st->forceFill(['recovery_step' => 'idle', 'recovery_started_at' => null, 'recovery_attempts' => 0])->save();
            }

            return $st;
        }

        if (! $this->enabled($device)) {
            return $st; // opt-in: sin autoaccion, solo queda visible/alertado (FLX-0047/0049)
        }

        // Seguridad: no reiniciar durante una OTA.
        if (data_get($device->health?->device_metrics, 'update.update_in_progress') === true) {
            $this->mark($st, 'esperando_ota', null);

            return $st;
        }

        $cfg = config('stability.recovery');
        // Cooldown entre acciones.
        if ($st->last_recovery_action_at && Carbon::now()->diffInSeconds($st->last_recovery_action_at, true) < $cfg['action_cooldown_s']) {
            return $st;
        }
        // Tope de acciones por ventana (anti-loop de reboot).
        if ($st->recovery_started_at && Carbon::now()->diffInSeconds($st->recovery_started_at, true) < 3600
            && $st->recovery_attempts >= $cfg['max_actions_window']) {
            $this->mark($st, 'tope_acciones', null);

            return $st;
        }

        $this->escalate($device, $st);

        return $st->fresh();
    }

    private function escalate(Device $device, DeviceStabilityState $st): void
    {
        $metrics = $device->health?->device_metrics ?? [];
        $relaunchLoop = $st->last_stability_event === 'activity_relaunch_loop'
            || data_get($metrics, 'recovery.requires_intervention') === true;
        // Codex R1: el reboot depende de `device_owner.reboot_available` (contrato que reporta VLS
        // DeviceOwnerManager.status()); fallback a device_owner_available para detectar DO.
        $deviceOwner = (bool) data_get($metrics, 'device_owner.reboot_available',
            data_get($metrics, 'device_owner.device_owner_available', false));

        if ($st->recovery_started_at === null) {
            $st->recovery_started_at = Carbon::now();
            $st->recovery_attempts = 0;
        }

        // Paso 1: siempre pedir diagnostico primero.
        if ($st->recovery_step === 'idle' || $st->recovery_step === 'esperando_ota') {
            $this->dispatch($device, $st, 'get_diagnostics', [], 'diagnostics');

            return;
        }

        // FLX-0050: en relaunch loop NO escalar a restart_app (realimenta el loop). Quedarse en HOLD con
        // diagnostico pedido; la accion correcta es detener relaunch (lo hace VLS-0069 en el equipo).
        if ($relaunchLoop) {
            $this->mark($st, 'hold_relaunch_loop', 'hold');

            return;
        }

        // Paso 2: reiniciar la app.
        if ($st->recovery_step === 'diagnostics') {
            $this->dispatch($device, $st, 'restart', ['level' => 'app'], 'restart');

            return;
        }

        // Paso 3: reiniciar el equipo (solo Device Owner) si persiste.
        if ($st->recovery_step === 'restart' && $deviceOwner) {
            $this->dispatch($device, $st, 'restart', ['level' => 'device'], 'reboot');

            return;
        }

        // Sin mas escalado disponible (no Device Owner): mantener y dejar visible.
        $this->mark($st, $deviceOwner ? 'reboot' : 'sin_escalado', $st->recovery_step);
    }

    private function dispatch(Device $device, DeviceStabilityState $st, string $cmd, array $params, string $step): void
    {
        $res = $this->dispatcher->dispatch($device, $cmd, $params, 'fcm');
        $commandId = $res['command_id'] ?? ($res['id'] ?? null);
        $st->forceFill([
            'recovery_step' => $step,
            'last_recovery_action' => $cmd.':'.$step.($commandId ? " (cmd {$commandId})" : ''),
            'last_recovery_action_at' => Carbon::now(),
            'recovery_attempts' => $st->recovery_attempts + 1,
        ])->save();
    }

    private function mark(DeviceStabilityState $st, string $action, ?string $step): void
    {
        $data = ['last_recovery_action' => $action, 'last_recovery_action_at' => Carbon::now()];
        if ($step !== null) {
            $data['recovery_step'] = $step;
        }
        $st->forceFill($data)->save();
    }

    private function enabled(Device $device): bool
    {
        // Opt-in por equipo via device_settings/global_settings (mismo patron que FLX-0041).
        $v = DeviceSetting::where('device_id', $device->id)->where('key', 'stability_recovery_enabled')->value('value')
            ?? GlobalSetting::where('key', 'stability_recovery_enabled')->value('value');
        if ($v === null) {
            return (bool) config('stability.recovery.enabled', false);
        }

        return in_array(strtolower((string) $v), ['1', 'true', 'si', 'yes', 'on'], true);
    }
}
