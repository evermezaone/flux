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
