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
    private const ALLOWED = ['snapshot', 'publish_clip', 'delete_clip', 'delete_all', 'config_update'];

    /** Operador (panel) encola un comando. Auth de operador (sesion). */
    public function enqueue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device' => ['required'],
            'cmd' => ['required', Rule::in(self::ALLOWED)],
            'params' => ['nullable', 'array'],
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

        $command = Command::create([
            'device_id' => $device->id,
            'cmd' => $data['cmd'],
            'params' => $params ?: null,
            'status' => 'pending',
        ]);
        $command->logEvent('created'); // trazabilidad (REQ-0015)

        return response()->json(['ok' => true, 'id' => $command->id], 201);
    }

    /** Dispositivo retira sus comandos pendientes; quedan 'sent'. */
    public function pull(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $pending = Command::where('device_id', $device->id)
            ->where('status', 'pending')
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
        ]);

        $command->update([
            'status' => $data['status'],
            'done_at' => now(),
            'result' => $data['result'] ?? null,
        ]);
        // Trazabilidad: registra la respuesta del dispositivo con su detalle (REQ-0015).
        $command->logEvent($data['status'], $data['result'] ?? null);

        return response()->json(['ok' => true]);
    }
}
