<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FLX-0044: ultimo estado de prerequisitos operativos de un equipo.
 */
class DeviceRequirementState extends Model
{
    protected $fillable = [
        'device_id', 'ok', 'critical_count', 'warning_count', 'failures',
        'failing_since', 'last_changed_at', 'last_recovery_at',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'critical_count' => 'integer',
        'warning_count' => 'integer',
        'failures' => 'array',
        'failing_since' => 'datetime',
        'last_changed_at' => 'datetime',
        'last_recovery_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
