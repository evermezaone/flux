<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    public const UPDATED_AT = null; // la tabla solo tiene created_at

    protected $fillable = ['site_id', 'code', 'device_key', 'model', 'last_seen_at', 'active'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'active' => 'boolean',
    ];

    protected $hidden = ['device_key']; // no exponer el token en respuestas

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(Telemetry::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(Command::class);
    }
}
