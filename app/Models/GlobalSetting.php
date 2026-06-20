<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuracion global (FLX REQ-0020): aplica a todos los equipos. PK = key.
 */
class GlobalSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'type'];
}
