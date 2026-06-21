<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Site;
use App\Services\Firebase\AppCheckVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Endurecimiento de la ingesta con Firebase App Check (FLX REQ-0029).
 * Se prueba sobre /api/v1/ping (endpoint device.key liviano).
 */
class AppCheckTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-ac', string $code = 'tel-01'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create([
            'site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true,
        ]);
    }

    public function test_modo_warn_permite_sin_token(): void
    {
        config(['firebase.appcheck_mode' => 'warn']);
        $this->device();

        // Sin X-Firebase-AppCheck: en warn NO bloquea.
        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k-ac'])->assertOk();
    }

    public function test_modo_off_permite_sin_token(): void
    {
        config(['firebase.appcheck_mode' => 'off']);
        $this->device();

        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k-ac'])->assertOk();
    }

    public function test_modo_enforce_rechaza_sin_token(): void
    {
        config(['firebase.appcheck_mode' => 'enforce']);
        $this->device();

        $this->getJson('/api/v1/ping', ['X-Device-Key' => 'k-ac'])->assertStatus(401);
    }

    public function test_modo_enforce_acepta_token_valido(): void
    {
        config(['firebase.appcheck_mode' => 'enforce']);
        $this->device();

        // Mock del verificador: no pega a Firebase.
        $mock = Mockery::mock(AppCheckVerifier::class);
        $mock->shouldReceive('verify')->with('tok-valido')->andReturn(true);
        $this->app->instance(AppCheckVerifier::class, $mock);

        $this->getJson('/api/v1/ping', [
            'X-Device-Key' => 'k-ac',
            'X-Firebase-AppCheck' => 'tok-valido',
        ])->assertOk();
    }

    public function test_modo_enforce_rechaza_token_invalido(): void
    {
        config(['firebase.appcheck_mode' => 'enforce']);
        $this->device();

        $mock = Mockery::mock(AppCheckVerifier::class);
        $mock->shouldReceive('verify')->with('tok-malo')->andReturn(false);
        $this->app->instance(AppCheckVerifier::class, $mock);

        $this->getJson('/api/v1/ping', [
            'X-Device-Key' => 'k-ac',
            'X-Firebase-AppCheck' => 'tok-malo',
        ])->assertStatus(401);
    }
}
