<?php

namespace Tests\Feature;

use App\Filament\Resources\Telemetry\Pages\ListTelemetry;
use App\Models\Device;
use App\Models\Site;
use App\Models\Telemetry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Listado de telemetria en el panel (FLX REQ-0023).
 */
class TelemetryPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurso_telemetria_lista_registros(): void
    {
        $user = User::factory()->create();
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);
        Telemetry::insert([
            'device_id' => $device->id, 'site_id' => $site->id, 'ts' => now(), 'client_seq' => 1,
            'zone' => 'CROSS', 'occupancy' => 5, 'pressure' => 5, 'congestion' => 'med', 'decision' => 'A_green',
            'battery_pct' => 80,
        ]);

        $this->actingAs($user)->get('/admin/telemetria')->assertOk();

        Livewire::actingAs($user)
            ->test(ListTelemetry::class)
            ->assertCanSeeTableRecords(Telemetry::all());
    }
}
