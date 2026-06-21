<?php

namespace Tests\Feature;

use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use App\Services\Fcm\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * FCM: registro de token + envio de push para despertar equipos (FLX REQ-0028).
 */
class FcmPushTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-fcm', string $code = 'tel-01'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create([
            'site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true,
        ]);
    }

    public function test_registro_de_token_requiere_device_key(): void
    {
        $this->postJson('/api/v1/fcm-token', ['token' => 'abc'])->assertStatus(401);
    }

    public function test_registra_token_fcm_del_equipo(): void
    {
        $d = $this->device();

        $this->postJson('/api/v1/fcm-token', ['token' => 'token-123'], ['X-Device-Key' => 'k-fcm'])
            ->assertOk()->assertJsonPath('ok', true);

        $d->refresh();
        $this->assertSame('token-123', $d->fcm_token);
        $this->assertNotNull($d->fcm_token_at);
    }

    public function test_fcm_token_no_se_expone_en_la_api(): void
    {
        $user = User::factory()->create();
        $d = $this->device();
        $d->forceFill(['fcm_token' => 'secreto'])->save();

        $this->actingAs($user)->getJson('/api/v1/devices')
            ->assertOk()
            ->assertDontSee('secreto');
    }

    public function test_accion_despertar_envia_push(): void
    {
        $user = User::factory()->create();
        $d = $this->device();
        $d->forceFill(['fcm_token' => 'tok-xyz'])->save();

        // Mock del sender: no pega a Firebase; verifica el payload.
        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldReceive('send')
            ->once()
            ->with('tok-xyz', ['action' => 'ping'])
            ->andReturn(true);
        $this->app->instance(FcmSender::class, $mock);

        Livewire::actingAs($user)
            ->test(ListDevices::class)
            ->callTableAction('despertar', $d, data: ['action' => 'ping']);
    }

    public function test_despertar_sin_token_no_envia(): void
    {
        $user = User::factory()->create();
        $d = $this->device(); // sin fcm_token

        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldNotReceive('send');
        $this->app->instance(FcmSender::class, $mock);

        Livewire::actingAs($user)
            ->test(ListDevices::class)
            ->callTableAction('despertar', $d, data: ['action' => 'ping']);
    }

    public function test_token_invalido_se_limpia(): void
    {
        $user = User::factory()->create();
        $d = $this->device();
        $d->forceFill(['fcm_token' => 'viejo'])->save();

        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldReceive('send')->once()->andReturn(false); // NotFound -> false
        $this->app->instance(FcmSender::class, $mock);

        Livewire::actingAs($user)
            ->test(ListDevices::class)
            ->callTableAction('despertar', $d, data: ['action' => 'ping']);

        $this->assertNull($d->refresh()->fcm_token);
    }
}
