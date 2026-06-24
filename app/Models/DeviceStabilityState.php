<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** FLX-0047: estado de estabilidad consolidado por equipo. */
class DeviceStabilityState extends Model
{
    protected $fillable = [
        'device_id', 'stability_status', 'crash_count_24h', 'anr_count_24h', 'ui_freeze_count_24h',
        'app_error_count_24h', 'event_count_24h', 'last_stability_event', 'last_stability_event_at',
        'ui_frozen', 'ui_last_tick_at', 'last_diagnostic_id', 'alerted_status',
        'recovery_step', 'recovery_started_at', 'last_recovery_action', 'last_recovery_action_at', 'recovery_attempts',
    ];

    protected $casts = [
        'crash_count_24h' => 'integer',
        'anr_count_24h' => 'integer',
        'ui_freeze_count_24h' => 'integer',
        'app_error_count_24h' => 'integer',
        'event_count_24h' => 'integer',
        'last_stability_event_at' => 'datetime',
        'ui_frozen' => 'boolean',
        'ui_last_tick_at' => 'datetime',
        'recovery_started_at' => 'datetime',
        'last_recovery_action_at' => 'datetime',
        'recovery_attempts' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
