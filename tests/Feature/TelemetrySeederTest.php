<?php

namespace Tests\Feature;

use App\Models\Telemetry;
use Database\Seeders\TelemetrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetrySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_y_es_repetible(): void
    {
        $this->seed(TelemetrySeeder::class);

        $n = Telemetry::count();
        $this->assertGreaterThan(900, $n); // ~1 semana cada 10 min
        $this->assertDatabaseHas('sites', ['code' => 'ruta2_cruce1']);
        $this->assertDatabaseHas('devices', ['code' => 'tel-ruta2-01']);

        // Repetible: re-correr no duplica (insertOrIgnore por (device_id, client_seq)).
        $this->seed(TelemetrySeeder::class);
        $this->assertSame($n, Telemetry::count());
    }

    public function test_pico_de_fin_de_semana(): void
    {
        $this->seed(TelemetrySeeder::class);
        $rows = Telemetry::select('ts', 'occupancy')->get();

        // Vie/Sab 16-20h (migracion saliente) vs Mar-Jue 2-4h (madrugada).
        $finde = $rows->filter(fn ($r) => in_array($r->ts->dayOfWeek, [5, 6], true) && $r->ts->hour >= 16 && $r->ts->hour <= 20)
            ->avg('occupancy');
        $madrugada = $rows->filter(fn ($r) => in_array($r->ts->dayOfWeek, [2, 3, 4], true) && $r->ts->hour >= 2 && $r->ts->hour <= 4)
            ->avg('occupancy');

        $this->assertGreaterThan($madrugada, $finde);
    }
}
