<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Site;
use App\Models\Telemetry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryQueryTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): array
    {
        $site = Site::create(['code' => 'ruta2_cruce1', 'name' => 'Cruce 1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);

        $base = ['device_id' => $device->id, 'site_id' => $site->id];
        // hora 21: 2 muestras (occ 5 + 9 = 14)
        Telemetry::create($base + ['ts' => '2026-06-19 21:00:00', 'client_seq' => 1, 'occupancy' => 5, 'pressure' => 5, 'queue_len_m' => 20, 'congestion' => 'med', 'wait_est_s' => 10, 'empty_s' => 2]);
        Telemetry::create($base + ['ts' => '2026-06-19 21:30:00', 'client_seq' => 2, 'occupancy' => 9, 'pressure' => 9, 'queue_len_m' => 40, 'congestion' => 'saturated', 'wait_est_s' => 30, 'empty_s' => 0]);
        // hora 22: 1 muestra (occ 3)
        Telemetry::create($base + ['ts' => '2026-06-19 22:10:00', 'client_seq' => 3, 'occupancy' => 3, 'pressure' => 3, 'queue_len_m' => 10, 'congestion' => 'low', 'wait_est_s' => 5, 'empty_s' => 5]);

        return [$site, $device];
    }

    public function test_consulta_requiere_operador(): void
    {
        $this->getJson('/api/v1/sites')->assertStatus(401);
    }

    public function test_lista_sites(): void
    {
        $this->seedData();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/sites')
            ->assertOk()
            ->assertJsonPath('sites.0.code', 'ruta2_cruce1');
    }

    public function test_devices_incluye_health(): void
    {
        $this->seedData();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/devices?site=ruta2_cruce1')
            ->assertOk()
            ->assertJsonPath('devices.0.code', 'tel-01')
            ->assertJsonPath('devices.0.health.ts', '2026-06-19T22:10:00.000000Z'); // ultimo snapshot (ISO-8601)
    }

    public function test_telemetry_raw(): void
    {
        $this->seedData();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/telemetry?site=ruta2_cruce1&agg=raw')
            ->assertOk()
            ->assertJsonPath('agg', 'raw')
            ->assertJsonCount(3, 'rows');
    }

    public function test_telemetry_hour_agrega_por_hora(): void
    {
        $this->seedData();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/telemetry?site=ruta2_cruce1&agg=hour')
            ->assertOk()
            ->assertJsonPath('agg', 'hour')
            ->assertJsonCount(2, 'rows') // hora 21 y 22
            ->assertJsonPath('rows.0.hora', '2026-06-19 21:00:00')
            ->assertJsonPath('rows.0.n', 2);
    }

    public function test_summary_kpis(): void
    {
        $this->seedData();
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/summary?site=ruta2_cruce1')
            ->assertOk()
            ->assertJsonPath('muestras', 3)
            ->assertJsonPath('saturacion_pct', 33.3) // 1 de 3
            ->assertJsonPath('hora_pico', '2026-06-19 21:00:00'); // mayor sum(occupancy)
    }
}
