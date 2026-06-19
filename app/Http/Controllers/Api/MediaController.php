<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
}
