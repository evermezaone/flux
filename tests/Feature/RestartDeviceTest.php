<?php

namespace Tests\Feature;

use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Models\Command;
use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reinicio remoto y programado de equipos (FLX REQ-0027).
 */
class RestartDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-r', string $code = 'tel-01'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create([
            'site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true,
        ]);
    }

    public function test_api_enqueue_permite_restart(): void
    {
        $user = User::factory()->create();
        $d = $this->device();

        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code,
            'cmd' => 'restart',
            'params' => ['level' => 'app'],
        ])->assertSuccessful();

        $this->assertDatabaseHas('commands', [
            'device_id' => $d->id, 'cmd' => 'restart', 'status' => 'pending',
        ]);
    }

    public function test_api_rechaza_restart_con_nivel_invalido(): void
    {
        // Obs 171: la API debe validar params.level (service|app|device).
        $user = User::factory()->create();
        $d = $this->device();

        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code,
            'cmd' => 'restart',
            'params' => ['level' => 'banana'],
        ])->assertStatus(422);

        // sin level tambien invalido
        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code,
            'cmd' => 'restart',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('commands', ['device_id' => $d->id, 'cmd' => 'restart']);
    }

    public function test_accion_panel_encola_restart(): void
    {
        $user = User::factory()->create();
        $d = $this->device();

        Livewire::actingAs($user)
            ->test(ListDevices::class)
            ->callTableAction('reiniciar', $d, data: ['level' => 'device']);

        $cmd = Command::where('device_id', $d->id)->where('cmd', 'restart')->first();
        $this->assertNotNull($cmd);
        $this->assertSame('device', $cmd->params['level']);
    }

    public function test_comando_programado_encola_a_todos_los_activos(): void
    {
        $d1 = $this->device('k1', 'tel-1');
        $d2 = $this->device('k2', 'tel-2');
        // inactivo: no debe recibir
        $d3 = $this->device('k3', 'tel-3');
        $d3->forceFill(['active' => false])->save();

        $this->artisan('devices:restart-scheduled --level=service')->assertExitCode(0);

        $this->assertDatabaseHas('commands', ['device_id' => $d1->id, 'cmd' => 'restart']);
        $this->assertDatabaseHas('commands', ['device_id' => $d2->id, 'cmd' => 'restart']);
        $this->assertDatabaseMissing('commands', ['device_id' => $d3->id, 'cmd' => 'restart']);
    }

    public function test_comando_programado_rechaza_nivel_invalido(): void
    {
        $this->device();
        $this->artisan('devices:restart-scheduled --level=banana')->assertExitCode(1);
    }
}
