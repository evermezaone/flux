<?php

namespace Tests\Feature;

use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_requiere_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_operador_ve_recursos(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/sites')->assertOk();
        $this->actingAs($user)->get('/admin/devices')->assertOk();
    }

    public function test_accion_de_comando_encola(): void
    {
        $user = User::factory()->create();
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ListDevices::class)
            ->callTableAction('comando', $device, data: ['cmd' => 'snapshot']);

        $this->assertDatabaseHas('commands', [
            'device_id' => $device->id,
            'cmd' => 'snapshot',
            'status' => 'pending',
        ]);
    }
}
