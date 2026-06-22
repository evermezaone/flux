<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Command;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cola de comandos FLX -> VLS.
 *  - enqueue: el operador (panel) encola un comando para un dispositivo.
 *  - pull:    el dispositivo (X-Device-Key) retira sus comandos pendientes (los marca 'sent').
 *  - ack:     el dispositivo confirma la ejecucion ('done'/'failed').
 */
class CommandController extends Controller
{
    private const ALLOWED = ['snapshot', 'publish_clip', 'publish_timelapse', 'delete_clip', 'delete_all', 'config_update', 'restart', 'clear_recovery', 'maintenance'];

    /** Operador (panel) encola un comando. Auth de operador (sesion). */
    public function enqueue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device' => ['required'],
            'cmd' => ['required', Rule::in(self::ALLOWED)],
            'params' => ['nullable', 'array'],
            // FLX-0035 / VLS-0043: canal solicitado. auto = FCM + polling (con anti-duplicado);
            // fcm = solo push; poll = solo cola (no push). Default auto.
            'channel' => ['nullable', Rule::in(['auto', 'fcm', 'poll'])],
        ]);

        $device = Device::where('code', $data['device'])
            ->when(is_numeric($data['device']), fn ($q) => $q->orWhere('id', $data['device']))
            ->first();

        if (! $device) {
            return response()->json(['ok' => false, 'error' => 'Dispositivo no encontrado'], 404);
        }

        // Validacion de params por tipo de comando.
        $params = $data['params'] ?? [];
        if ($data['cmd'] === 'publish_clip' && empty($params['ts'])) {
            return response()->json(['ok' => false, 'error' => 'publish_clip requiere params.ts'], 422);
        }
        if ($data['cmd'] === 'delete_clip' && empty($params['file'])) {
            return response()->json(['ok' => false, 'error' => 'delete_clip requiere params.file'], 422);
        }
        // Obs 171: validar el nivel de restart (la app solo entiende service|app|device).
        if ($data['cmd'] === 'restart' && ! in_array($params['level'] ?? null, ['service', 'app', 'device'], true)) {
            return response()->json(['ok' => false, 'error' => 'restart requiere params.level: service|app|device'], 422);
        }
        // Obs 182: maintenance requiere params.enabled booleano (la app activa/desactiva el modo segun ese flag).
        if ($data['cmd'] === 'maintenance' && ! is_bool($params['enabled'] ?? null)) {
            return response()->json(['ok' => false, 'error' => 'maintenance requiere params.enabled (booleano)'], 422);
        }

        $channel = $data['channel'] ?? 'auto';

        // FLX-0035: encolar + push segun el canal (punto unico, compartido con las acciones del panel).
        $result = app(\App\Services\CommandDispatcher::class)
            ->dispatch($device, $data['cmd'], $params ?: null, $channel);

        return response()->json([
            'ok' => true,
            'id' => $result['command']->id,
            'channel' => $channel,
            'pushed' => $result['pushed'],
        ], 201);
    }

    /** Dispositivo retira sus comandos pendientes; quedan 'sent'. */
    public function pull(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        // FLX-0035: el polling NO entrega los comandos de canal 'fcm' (esos van solo por push). Sí entrega
        // 'poll' y 'auto' (en auto, el push y el polling conviven con anti-duplicado por id en el equipo).
        $pending = Command::where('device_id', $device->id)
            ->where('status', 'pending')
            ->whereIn('channel', ['auto', 'poll'])
            ->orderBy('id')
            ->get();

        if ($pending->isNotEmpty()) {
            Command::whereIn('id', $pending->pluck('id'))
                ->update(['status' => 'sent', 'picked_at' => now()]);
            // Trazabilidad: marca cada comando como entregado al dispositivo (REQ-0015).
            foreach ($pending as $c) {
                $c->logEvent('sent');
            }
        }

        return response()->json([
            'ok' => true,
            'commands' => $pending->map(fn (Command $c) => [
                'id' => $c->id,
                'cmd' => $c->cmd,
                'params' => $c->params,
            ])->values(),
        ]);
    }

    /** Dispositivo confirma la ejecucion del comando. */
    public function ack(Request $request, Command $command): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        if ($command->device_id !== $device->id) {
            return response()->json(['ok' => false, 'error' => 'El comando no pertenece a este dispositivo'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:done,failed'],
            'result' => ['nullable', 'string', 'max:1000'], // detalle reportado por el dispositivo (REQ-0015)
            // FLX-0035: canal REAL por el que el equipo ejecuto (fcm|poll), para ver por donde se fue.
            'exec_channel' => ['nullable', Rule::in(['fcm', 'poll'])],
        ]);

        // Anti doble ejecucion: si el comando ya estaba 'done', no re-procesar el ack (idempotente).
        if ($command->status === 'done') {
            return response()->json(['ok' => true, 'already' => true]);
        }

        $command->update([
            'status' => $data['status'],
            'done_at' => now(),
            'result' => $data['result'] ?? null,
            'exec_channel' => $data['exec_channel'] ?? $command->exec_channel,
        ]);
        // Trazabilidad: registra la respuesta del dispositivo con su detalle (REQ-0015).
        $command->logEvent($data['status'], $data['result'] ?? null);

        return response()->json(['ok' => true]);
    }
}
