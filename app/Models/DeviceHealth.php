<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ultimo estado de salud por equipo (FLX REQ-0026). Una fila por dispositivo (upsert por device_id).
 */
class DeviceHealth extends Model
{
    protected $table = 'device_health';

    protected $fillable = [
        'device_id', 'overall', 'subsystems', 'device_metrics',
        'uptime_s', 'app_version', 'app_build', 'reported_at', 'alerted',
    ];

    protected $casts = [
        'subsystems' => 'array',
        'device_metrics' => 'array',
        'reported_at' => 'datetime',
        'alerted' => 'boolean',
        'uptime_s' => 'integer',
        'app_build' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** Minutos desde el ultimo latido (null si nunca reporto). */
    public function minutesSinceReport(): ?int
    {
        return $this->reported_at ? (int) $this->reported_at->diffInMinutes(now()) : null;
    }

    /**
     * Estado efectivo considerando antiguedad del latido.
     * offline si supera el umbral; si no, el overall reportado.
     */
    public function effectiveStatus(int $offlineMinutes): string
    {
        $age = $this->minutesSinceReport();
        if ($age === null || $age >= $offlineMinutes) {
            return 'offline';
        }

        return $this->overall;
    }
}
