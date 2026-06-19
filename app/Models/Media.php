<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $table = 'media';
    public const UPDATED_AT = null; // la tabla solo tiene created_at

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
