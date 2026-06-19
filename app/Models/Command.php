<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Command extends Model
{
    public const UPDATED_AT = null; // created_at + picked_at + done_at (sin updated_at)

    protected $fillable = ['device_id', 'cmd', 'params', 'status', 'picked_at', 'done_at'];

    protected $casts = [
        'params' => 'array',
        'picked_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
