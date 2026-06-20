<?php

namespace Tests\Feature;

use App\Filament\Widgets\EquipmentChart;
use App\Filament\Widgets\TrafficChart;
use App\Models\Device;
use App\Models\Site;
use App\Models\Telemetry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelChartsTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): void
    {
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);
        $base = ['device_id' => $device->id, 'site_id' => $site->id];
        Telemetry::create($base + ['ts' => now()->subHours(2), 'client_seq' => 1, 'occupancy' => 5, 'pressure' => 5, 'queue_len_m' => 20, 'battery_pct' => 80, 'temp_c' => 38, 'cpu_pct' => 40, 'mem_pct' => 50, 'storage_free_pct' => 60]);
        Telemetry::create($base + ['ts' => now()->subHour(), 'client_seq' => 2, 'occupancy' => 9, 'pressure' => 9, 'queue_len_m' => 40, 'battery_pct' => 70, 'temp_c' => 41, 'cpu_pct' => 55, 'mem_pct' => 60, 'storage_free_pct' => 58]);
    }

    public function test_chart_de_trafico_renderiza(): void
    {
        $this->seedData();

        Livewire::actingAs(User::factory()->create())
            ->test(TrafficChart::class)
            ->assertOk();
    }

    public function test_chart_de_equipo_renderiza(): void
    {
        $this->seedData();

        Livewire::actingAs(User::factory()->create())
            ->test(EquipmentChart::class)
            ->assertOk();
    }
}
