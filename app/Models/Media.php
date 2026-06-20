<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';
    public const UPDATED_AT = null; // la tabla solo tiene created_at

    /**
     * Al borrar un media (fila o bulk delete) se elimina tambien el archivo fisico del storage
     * para no dejar huerfanos (REQ-0019). Si no existe, no falla.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $media): void {
            if (filled($media->file)) {
                Storage::disk('local')->delete('media/'.basename($media->file));
            }
        });
    }

    protected $fillable = [
        'device_id', 'site_id', 'tipo', 'ts_start', 'ts_end',
        'file', 'fps', 'size_mb', 'available', 'url',
    ];

    protected $casts = [
        'ts_start' => 'datetime',
        'ts_end' => 'datetime',
        'fps' => 'integer',
        'size_mb' => 'decimal:2',
        'available' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
