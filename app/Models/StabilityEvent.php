<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** FLX-0047: un evento de estabilidad reportado por VLS/Sentinel. */
class StabilityEvent extends Model
{
    protected $fillable = [
        'device_id', 'event_id', 'event_type', 'severity', 'occurred_at', 'recovered_at',
        'app_version', 'sentinel_version', 'summary', 'details', 'diagnostic_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'recovered_at' => 'datetime',
        'details' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
