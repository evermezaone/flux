<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0061: la config efectiva habilita device-owner features + reboot del equipo (Device Owner) por defecto,
 * para que el comando 'restart' (level=device) y el auto-curado (VLS-0095) puedan reiniciar el equipo.
 */
class DeviceRebootSettingTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key, string $code): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create(['site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true]);
    }

    public function test_config_habilita_features_y_reboot_del_equipo(): void
    {
        $this->device('k-rb', 'tel-rb');

        $res = $this->getJson('/api/v1/config', ['X-Device-Key' => 'k-rb'])->assertOk();

        // los dos flags que VLS.DeviceOwnerManager.rebootAvailable() exige
        $res->assertJsonPath('config.device_owner_features_enabled', 'true');
        $res->assertJsonPath('config.allow_device_reboot', 'true');
    }

    public function test_se_puede_deshabilitar_el_reboot_por_equipo(): void
    {
        $d = $this->device('k-no', 'tel-no');
        // un equipo puntual puede pisar el global y NO permitir reboot
        DeviceSetting::create(['device_id' => $d->id, 'key' => 'allow_device_reboot', 'value' => 'false', 'type' => 'bool']);

        $this->getJson('/api/v1/config', ['X-Device-Key' => 'k-no'])
            ->assertOk()
            ->assertJsonPath('config.allow_device_reboot', 'false'); // device pisa global
    }
}
