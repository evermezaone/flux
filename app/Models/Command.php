<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Command extends Model
{
    public const UPDATED_AT = null; // created_at + picked_at + done_at (sin updated_at)

    protected $fillable = ['device_id', 'cmd', 'params', 'status', 'picked_at', 'done_at', 'result'];

    protected $casts = [
        'params' => 'array',
        'picked_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** Bitacora del ciclo de vida (REQ-0015). */
    public function events(): HasMany
    {
        return $this->hasMany(CommandEvent::class)->orderBy('id');
    }

    /** Registra un evento de trazabilidad (created|sent|done|failed) con nota opcional. */
    public function logEvent(string $event, ?string $note = null): void
    {
        $this->events()->create([
            'device_id' => $this->device_id,
            'event' => $event,
            'note' => $note,
        ]);
    }
}
