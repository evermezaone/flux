<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuracion por equipo (FLX REQ-0020): PK compuesta (device_id, key).
 * Eloquent no soporta PK compuesta nativamente; se sobreescribe setKeysForSaveQuery para que
 * save()/delete() usen ambas columnas. Las operaciones se hacen por updateOrCreate / query builder.
 */
class DeviceSetting extends Model
{
    public $incrementing = false;

    protected $fillable = ['device_id', 'key', 'value', 'type'];

    protected $casts = ['device_id' => 'integer'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** PK compuesta: las escrituras/borrados filtran por (device_id, key). */
    protected function setKeysForSaveQuery($query)
    {
        return $query->where('device_id', $this->getAttribute('device_id'))
            ->where('key', $this->getAttribute('key'));
    }
}
