<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limit_de_ingesta_por_device(): void
    {
        $site = Site::create(['code' => 'ruta2_cruce1']);
        Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k-123', 'active' => true,
        ]);

        // INGESTA_RATE_LIMIT=3 en phpunit.xml: 3 OK, el 4to 429.
        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k-123'])->assertOk();
        }
        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k-123'])->assertStatus(429);
    }

    public function test_cabeceras_de_seguridad(): void
    {
        $this->get('/up')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_rate_limit_lee_el_valor_desde_config(): void
    {
        // El limiter toma el valor de config('ingesta.rate_limit'), no de env() en runtime.
        config(['ingesta.rate_limit' => 2]);

        $site = Site::create(['code' => 'cruce2']);
        Device::create([
            'site_id' => $site->id, 'code' => 'tel-02', 'device_key' => 'k-2', 'active' => true,
        ]);

        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k-2'])->assertOk();
        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k-2'])->assertOk();
        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k-2'])->assertStatus(429);
    }
}
