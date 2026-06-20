<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento del ciclo de vida de un comando (FLX REQ-0015): created|sent|done|failed.
 * Append-only; sin updated_at.
 */
class CommandEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['command_id', 'device_id', 'event', 'note'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }
}
