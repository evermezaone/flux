<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| FLX-0061: habilitar device-owner features + REBOOT del equipo (Device Owner) para los sensores dedicados.
| Sin estos dos flags, VLS.DeviceOwnerManager.rebootAvailable() = false -> el comando 'restart' con level=device
| y el auto-curado (VLS-0095, escala a REBOOT_DEVICE) NO reinician el equipo (se rechazan por configuracion).
| Global (aplica a todos los equipos). Se puede pisar per-device con DeviceSetting si algun equipo NO debe reiniciarse.
*/
return new class extends Migration
{
    private array $settings = [
        'device_owner_features_enabled' => 'true',
        'allow_device_reboot' => 'true',
    ];

    public function up(): void
    {
        foreach ($this->settings as $key => $value) {
            DB::table('global_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'type' => 'bool', 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('global_settings')->whereIn('key', array_keys($this->settings))->delete();
    }
};
