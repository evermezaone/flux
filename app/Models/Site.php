<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    public const UPDATED_AT = null; // la tabla solo tiene created_at

    protected $fillable = [
        'code', 'name', 'lat', 'lng',
        'location_manual', 'location_accuracy_m', 'location_updated_at',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'location_manual' => 'boolean',
        'location_accuracy_m' => 'decimal:2',
        'location_updated_at' => 'datetime',
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
