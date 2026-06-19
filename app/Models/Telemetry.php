<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Telemetry extends Model
{
    protected $table = 'telemetry';     // evita la pluralizacion a "telemetries"
    public $timestamps = false;         // append-only; created_at lo pone la BD (useCurrent)

    protected $fillable = [
        'device_id', 'site_id', 'ts', 'client_seq',
        'zone', 'occupancy', 'queue_len_m', 'pressure', 'congestion',
        'decision', 'wait_est_s', 'empty_s',
        'battery_pct', 'temp_c', 'cpu_pct', 'mem_pct', 'storage_free_pct',
    ];

    protected $casts = [
        'ts' => 'datetime',
        'client_seq' => 'integer',
        'occupancy' => 'integer',
        'queue_len_m' => 'decimal:2',
        'pressure' => 'integer',
        'wait_est_s' => 'decimal:2',
        'empty_s' => 'integer',
        'temp_c' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
