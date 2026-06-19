<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_sin_device_key_devuelve_401_json(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertStatus(401)
            ->assertJson(['ok' => false]);
    }

    public function test_ping_con_device_key_valido_devuelve_200(): void
    {
        $site = Site::create(['code' => 'ruta2_cruce1', 'name' => 'Cruce 1']);
        Device::create([
            'site_id' => $site->id,
            'code' => 'tel-01',
            'device_key' => 'clave-de-prueba-123',
            'active' => true,
        ]);

        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'clave-de-prueba-123'])
            ->assertOk()
            ->assertJson(['ok' => true, 'device' => 'tel-01']);
    }

    public function test_ping_con_device_inactivo_devuelve_401(): void
    {
        $site = Site::create(['code' => 'cruce2']);
        Device::create([
            'site_id' => $site->id,
            'code' => 'tel-02',
            'device_key' => 'k2',
            'active' => false,
        ]);

        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k2'])
            ->assertStatus(401);
    }
}
