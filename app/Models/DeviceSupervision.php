<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FLX-0041: estado del supervisor remoto de un equipo (maquina de estados + escalamiento).
 */
class DeviceSupervision extends Model
{
    protected $fillable = [
        'device_id', 'state', 'step', 'last_action', 'last_action_channel',
        'last_action_result', 'last_action_at', 'reason', 'window_started_at', 'window_count',
    ];

    protected $casts = [
        'last_action_at' => 'datetime',
        'window_started_at' => 'datetime',
        'step' => 'integer',
        'window_count' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
