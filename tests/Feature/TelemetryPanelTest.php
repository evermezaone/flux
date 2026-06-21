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
 * Listado de telemetria en el panel (FLX REQ-0023) + filtros y exportador CSV (FLX REQ-0024).
 */
class TelemetryPanelTest extends TestCase
{
    use RefreshDatabase;

    private function seedTelemetry(): array
    {
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);
        Telemetry::insert([
            'device_id' => $device->id, 'site_id' => $site->id, 'ts' => now(), 'client_seq' => 1,
            'zone' => 'CROSS', 'occupancy' => 5, 'pressure' => 5, 'congestion' => 'med', 'decision' => 'A_green',
            'battery_pct' => 80,
        ]);

        return [$site, $device];
    }

    public function test_recurso_telemetria_lista_registros(): void
    {
        $user = User::factory()->create();
        $this->seedTelemetry();

        $this->actingAs($user)->get('/admin/telemetria')->assertOk();

        Livewire::actingAs($user)
            ->test(ListTelemetry::class)
            ->assertCanSeeTableRecords(Telemetry::all());
    }

    public function test_filtro_rango_fechas_acota_registros(): void
    {
        $user = User::factory()->create();
        [$site, $device] = $this->seedTelemetry();

        // Un registro viejo (fuera del rango) y el reciente del seed (dentro del rango).
        $vieja = Telemetry::create([
            'device_id' => $device->id, 'site_id' => $site->id, 'ts' => now()->subDays(10),
            'client_seq' => 2, 'zone' => 'CROSS', 'occupancy' => 1, 'pressure' => 1,
            'congestion' => 'low', 'decision' => 'B_green', 'battery_pct' => 90,
        ]);
        $reciente = Telemetry::where('client_seq', 1)->first();

        Livewire::actingAs($user)
            ->test(ListTelemetry::class)
            ->filterTable('ts_range', ['desde' => now()->subDay()->toDateString()])
            ->assertCanSeeTableRecords([$reciente])
            ->assertCanNotSeeTableRecords([$vieja]);
    }

    public function test_filtro_por_dispositivo_acota_registros(): void
    {
        $user = User::factory()->create();
        [$site, $device] = $this->seedTelemetry();

        $otro = Device::create([
            'site_id' => $site->id, 'code' => 'tel-02', 'device_key' => 'k2', 'active' => true,
        ]);
        $ajeno = Telemetry::create([
            'device_id' => $otro->id, 'site_id' => $site->id, 'ts' => now(),
            'client_seq' => 1, 'zone' => 'CROSS', 'occupancy' => 2, 'pressure' => 2,
            'congestion' => 'low', 'decision' => 'A_green', 'battery_pct' => 70,
        ]);
        $propio = Telemetry::where('device_id', $device->id)->first();

        Livewire::actingAs($user)
            ->test(ListTelemetry::class)
            ->filterTable('device_id', $device->id)
            ->assertCanSeeTableRecords([$propio])
            ->assertCanNotSeeTableRecords([$ajeno]);
    }

    public function test_exportar_csv_dispara_descarga(): void
    {
        $user = User::factory()->create();
        $this->seedTelemetry();

        Livewire::actingAs($user)
            ->test(ListTelemetry::class)
            ->callAction('exportar_csv')
            ->assertFileDownloaded();
    }
}
