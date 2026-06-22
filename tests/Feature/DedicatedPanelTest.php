<?php

namespace Tests\Feature;

use App\Filament\Resources\DeviceHealthResource\Pages\ListDeviceHealth;
use App\Models\Command;
use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel FLX para equipo dedicado (FLX REQ-0031): visibilidad de supervivencia + acciones de mantenimiento.
 */
class DedicatedPanelTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-ded', string $code = 'tel-01'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create(['site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true]);
    }

    public function test_panel_salud_muestra_campos_de_supervivencia(): void
    {
        $user = User::factory()->create();
        $d = $this->device();
        DeviceHealth::create([
            'device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now(),
            'device_metrics' => [
                'requires_intervention' => true,
                'sentinel' => ['last_sentinel_action' => 'VLS relanzado', 'launch_count_hour' => '2', 'launch_count_day' => '3'],
                'device_owner' => ['device_owner_available' => true, 'kiosk_active' => true, 'reboot_available' => false],
                'recovery' => ['recovery_count_hour' => 1, 'recovery_count_day' => 4, 'last_recovery_action' => ['source' => 'watchdog', 'level' => 'restart_app', 'result' => 'ok']],
            ],
        ]);

        $this->actingAs($user)->get('/admin/salud')->assertOk();
        Livewire::actingAs($user)->test(ListDeviceHealth::class)->assertOk();
    }

    public function test_api_permite_clear_recovery_y_maintenance(): void
    {
        $user = User::factory()->create();
        $d = $this->device();

        $this->actingAs($user)->postJson('/api/v1/commands', ['device' => $d->code, 'cmd' => 'clear_recovery'])->assertSuccessful();
        $this->actingAs($user)->postJson('/api/v1/commands', ['device' => $d->code, 'cmd' => 'maintenance', 'params' => ['enabled' => true]])->assertSuccessful();

        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'clear_recovery']);
        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'maintenance']);
    }

    public function test_maintenance_exige_enabled_booleano(): void
    {
        // Obs 182: el endpoint generico no debe encolar maintenance sin params.enabled booleano.
        $user = User::factory()->create();
        $d = $this->device();

        // Falta params.enabled -> 422
        $this->actingAs($user)->postJson('/api/v1/commands', ['device' => $d->code, 'cmd' => 'maintenance'])
            ->assertStatus(422);

        // enabled no booleano (string) -> 422
        $this->actingAs($user)->postJson('/api/v1/commands', ['device' => $d->code, 'cmd' => 'maintenance', 'params' => ['enabled' => 'si']])
            ->assertStatus(422);

        // enabled booleano -> 201 (se encola)
        $this->actingAs($user)->postJson('/api/v1/commands', ['device' => $d->code, 'cmd' => 'maintenance', 'params' => ['enabled' => false]])
            ->assertSuccessful();
        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'maintenance']);
    }

    public function test_accion_mantenimiento_encola_comando(): void
    {
        $user = User::factory()->create();
        $d = $this->device();
        DeviceHealth::create(['device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now()]);

        Livewire::actingAs($user)
            ->test(ListDeviceHealth::class)
            ->callTableAction('mantenimiento', DeviceHealth::first(), data: ['op' => 'maintenance_on']);

        $cmd = Command::where('device_id', $d->id)->where('cmd', 'maintenance')->first();
        $this->assertNotNull($cmd);
        $this->assertTrue($cmd->params['enabled']);
    }
}
