<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\GlobalSetting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Configuracion global/por equipo (FLX REQ-0020): endpoint /config (efectiva), comando config_update
 * permitido, y carga del panel.
 */
class ConfigTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-123', string $code = 'tel-01'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create([
            'site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true,
        ]);
    }

    public function test_config_requiere_device_key(): void
    {
        $this->getJson('/api/v1/config')->assertStatus(401);
    }

    public function test_config_efectiva_global_mas_overrides(): void
    {
        $d = $this->device();
        GlobalSetting::create(['key' => 'telemetry_interval_s', 'value' => '15', 'type' => 'int']);
        GlobalSetting::create(['key' => 'clip_duration_s', 'value' => '6', 'type' => 'int']);
        // override por equipo: clip_duration_s pisa al global
        DeviceSetting::create(['device_id' => $d->id, 'key' => 'clip_duration_s', 'value' => '10', 'type' => 'int']);
        DeviceSetting::create(['device_id' => $d->id, 'key' => 'site_id', 'value' => 'ruta2_cruce1', 'type' => 'string']);

        $res = $this->getJson('/api/v1/config', ['X-Device-Key' => 'k-123'])->assertOk();

        $res->assertJsonPath('config.telemetry_interval_s', '15');
        $res->assertJsonPath('config.clip_duration_s', '10'); // device pisa global
        $res->assertJsonPath('config.site_id', 'ruta2_cruce1');
    }

    public function test_config_de_un_equipo_no_incluye_overrides_de_otro(): void
    {
        $d1 = $this->device('k-1', 'tel-1');
        $d2 = $this->device('k-2', 'tel-2');
        DeviceSetting::create(['device_id' => $d2->id, 'key' => 'foo', 'value' => 'bar']);

        $this->getJson('/api/v1/config', ['X-Device-Key' => 'k-1'])
            ->assertOk()
            ->assertJsonMissingPath('config.foo');
    }

    public function test_config_update_es_comando_permitido(): void
    {
        $this->device();
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/commands', ['device' => 'tel-01', 'cmd' => 'config_update'])
            ->assertCreated();

        $this->assertDatabaseHas('commands', ['cmd' => 'config_update', 'status' => 'pending']);
    }

    public function test_publish_timelapse_es_comando_permitido(): void
    {
        $this->device();
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/commands', ['device' => 'tel-01', 'cmd' => 'publish_timelapse', 'params' => ['file' => 'timelapse_x']])
            ->assertCreated();

        $this->assertDatabaseHas('commands', ['cmd' => 'publish_timelapse', 'status' => 'pending']);
    }

    public function test_panel_configuracion_carga(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/configuracion')
            ->assertOk();
    }

    public function test_device_setting_pk_compuesta_upsert(): void
    {
        $d = $this->device();
        DeviceSetting::updateOrCreate(['device_id' => $d->id, 'key' => 'k'], ['value' => 'a']);
        DeviceSetting::updateOrCreate(['device_id' => $d->id, 'key' => 'k'], ['value' => 'b']);

        $this->assertSame(1, DeviceSetting::where('device_id', $d->id)->where('key', 'k')->count());
        $this->assertSame('b', DeviceSetting::where('device_id', $d->id)->where('key', 'k')->value('value'));
    }
}
