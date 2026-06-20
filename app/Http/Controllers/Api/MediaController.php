<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registro de metadatos de video (VialSense -> FLX). Upsert por 'file'.
 * El archivo de video vive en el telefono; aca solo van los metadatos.
 */
class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'tipo' => ['required', 'in:timelapse,clip'],
            'file' => ['required', 'string', 'max:255'],
            'ts_start' => ['nullable', 'date'],
            'ts_end' => ['nullable', 'date'],
            'fps' => ['nullable', 'integer', 'min:0'],
            'size_mb' => ['nullable', 'numeric', 'min:0'],
            'available' => ['nullable', 'boolean'],
        ]);

        $media = Media::updateOrCreate(
            ['file' => $data['file']],
            [
                'device_id' => $device->id,
                'site_id' => $device->site_id,
                'tipo' => $data['tipo'],
                'ts_start' => isset($data['ts_start']) ? Carbon::parse($data['ts_start'])->utc() : null,
                'ts_end' => isset($data['ts_end']) ? Carbon::parse($data['ts_end'])->utc() : null,
                'fps' => $data['fps'] ?? null,
                'size_mb' => $data['size_mb'] ?? null,
                'available' => $data['available'] ?? true,
            ]
        );

        return response()->json([
            'ok' => true,
            'id' => $media->id,
            'created' => $media->wasRecentlyCreated,
        ]);
    }

    /**
     * Subida de un clip puntual a demanda (VialSense -> FLX). Multipart.
     * Guarda el archivo, hace upsert del media por 'file' y setea url de descarga.
     */
    public function upload(Request $request): JsonResponse
    {
        /** @var \App\Models\Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'file' => ['required', 'string', 'max:255'],   // nombre logico (ej. 20260619_1830.mp4)
            'tipo' => ['required', 'in:timelapse,clip'],
            'archivo' => ['required', 'file', 'mimes:mp4,jpg,jpeg,png', 'max:102400'], // 100 MB
        ]);

        $name = basename($data['file']); // evita traversal
        $request->file('archivo')->storeAs('media', $name, 'local');
        $sizeMb = round($request->file('archivo')->getSize() / 1048576, 2);

        $media = Media::updateOrCreate(
            ['file' => $name],
            [
                'device_id' => $device->id,
                'site_id' => $device->site_id,
                'tipo' => $data['tipo'],
                'size_mb' => $sizeMb,
                'available' => true,
            ]
        );
        // URL absoluta que respeta el subdirectorio del despliegue (/flux), via route nombrada (REQ-0016).
        $media->update(['url' => route('media.download', $media)]);

        return response()->json(['ok' => true, 'id' => $media->id, 'url' => $media->url]);
    }

    /** Descarga del clip subido (panel/operador). */
    public function download(Media $media): StreamedResponse
    {
        $path = 'media/'.basename($media->file);

        abort_if(blank($media->url) || ! Storage::disk('local')->exists($path), 404, 'Archivo no disponible');

        return Storage::disk('local')->download($path);
    }
}
