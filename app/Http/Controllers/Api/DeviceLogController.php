<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FLX-0039: recepcion y descarga de paquetes de logs del equipo (VLS/Sentinel) para diagnosticar fallos
 * en campo. Upload por X-Device-Key (lo hace el equipo via get_logs, VLS-0054); descarga por el operador
 * (sesion). El archivo se guarda en storage PRIVADO ('local' = storage/app), nunca publico directo.
 */
class DeviceLogController extends Controller
{
    /** Extensiones aceptadas para el paquete de logs. */
    private const EXTS = ['zip', 'txt', 'jsonl', 'json', 'log', 'gz'];

    /** El equipo sube su paquete de logs. Auth: X-Device-Key (middleware device.key). */
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50 MB
            'source' => ['nullable', 'in:vls,sentinel,combined,system'],
            'summary' => ['nullable', 'string', 'max:500'],
            'build' => ['nullable', 'string', 'max:64'],
        ]);

        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($ext, self::EXTS, true)) {
            return response()->json(['ok' => false, 'error' => 'extension no permitida'], 422);
        }

        // Nombre seguro y unico (sin traversal): logs/<device>/<timestamp>.<ext>
        $name = $device->code.'_'.now()->format('Ymd_His').'.'.$ext;
        $path = $request->file('file')->storeAs('device-logs/'.$device->id, $name, 'local');

        $log = DeviceLog::create([
            'device_id' => $device->id,
            'source' => $data['source'] ?? 'vls',
            'build' => $data['build'] ?? null,
            'summary' => $data['summary'] ?? null,
            'path' => $path,
            'size' => $request->file('file')->getSize(),
            'reported_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $log->id, 'size' => $log->size]);
    }

    /** El operador descarga un paquete de logs. Auth: sesion (middleware auth). */
    public function download(DeviceLog $deviceLog): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($deviceLog->path), 404);

        return Storage::disk('local')->download($deviceLog->path);
    }
}
