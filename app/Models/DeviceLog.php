<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FLX-0039: paquete de logs subido por el equipo (VLS/Sentinel). El archivo vive en storage privado
 * ('local' disk), aca solo la metadata + la ruta.
 */
class DeviceLog extends Model
{
    protected $fillable = [
        'device_id', 'source', 'build', 'summary', 'path', 'size', 'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'size' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
