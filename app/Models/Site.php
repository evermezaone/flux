<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    public const UPDATED_AT = null; // la tabla solo tiene created_at

    protected $fillable = ['code', 'name', 'lat', 'lng'];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(Telemetry::class);
    }
}
