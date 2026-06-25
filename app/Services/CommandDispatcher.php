<?php

namespace App\Services;

use App\Models\Command;
use App\Models\Device;
use App\Services\Fcm\FcmSender;

/**
 * Encola un comando y, segun el CANAL, lo empuja por FCM (FLX-0035 / VLS-0043). Punto unico usado por el
 * endpoint de ingesta (CommandController::enqueue) y por las acciones del panel (Reiniciar / Mantenimiento),
 * para que el canal y el push se comporten igual desde donde sea.
 *
 * Canales:
 *  - auto: FCM (si hay token) + cola para polling. La app deduplica por id -> sin doble ejecucion.
 *  - fcm:  solo push (el polling no lo entrega).
 *  - poll: solo cola (no push).
 */
class CommandDispatcher
{
    public function __construct(private FcmSender $fcm) {}

    /**
     * @param  array<string,mixed>|null  $params
     * @return array{command: Command, pushed: bool}
     */
    public function dispatch(Device $device, string $cmd, ?array $params, string $channel = 'auto'): array
    {
        // FLX-0040: el reinicio de TELEFONO (level=device) es destructivo y se usa cuando el equipo esta
        // trabado (no consulta la cola). Forzar FCM puro (no auto, no poll): el polling NO entrega los
        // comandos de canal 'fcm', asi un reboot no queda pendiente en cola y NO se re-ejecuta post-reboot.
        if ($cmd === 'restart' && ($params['level'] ?? null) === 'device') {
            $channel = 'fcm';
        }
        // VLS-0084 / FLX-0053: un equipo detenido (stop_all) NO consulta la cola (polling apagado), pero FCM si
        // arranca el proceso. El "Reanudar" debe ir por FCM puro para que llegue al equipo detenido.
        if (in_array($cmd, ['resume', 'start_all'], true)) {
            $channel = 'fcm';
        }

        $command = Command::create([
            'device_id' => $device->id,
            'cmd' => $cmd,
            'channel' => $channel,
            'params' => $params ?: null,
            'status' => 'pending',
        ]);
        $command->logEvent('created');

        $pushed = in_array($channel, ['auto', 'fcm'], true) && $this->push($device, $command);

        return ['command' => $command, 'pushed' => $pushed];
    }

    /** Empuja el comando por FCM. Best-effort: si falla, queda en la cola (para auto/poll). */
    private function push(Device $device, Command $command): bool
    {
        if (blank($device->fcm_token)) {
            return false;
        }

        try {
            return $this->fcm->send($device->fcm_token, [
                'action' => 'command',
                'command_id' => (string) $command->id,
                'cmd' => (string) $command->cmd,
                'params' => json_encode($command->params ?: (object) []),
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
