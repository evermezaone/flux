<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Site;
use App\Models\Telemetry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purga_borra_vieja_conserva_reciente(): void
    {
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);
        $base = ['device_id' => $device->id, 'site_id' => $site->id];

        Telemetry::create($base + ['ts' => now()->subDays(100), 'client_seq' => 1, 'occupancy' => 5]);
        Telemetry::create($base + ['ts' => now()->subDays(1), 'client_seq' => 2, 'occupancy' => 3]);

        $this->artisan('telemetry:purge --days=90')->assertExitCode(0);

        $this->assertSame(1, Telemetry::count());
        $this->assertDatabaseMissing('telemetry', ['client_seq' => 1]);
        $this->assertDatabaseHas('telemetry', ['client_seq' => 2]);
    }

    public function test_default_lee_retencion_desde_config(): void
    {
        // Sin --days, el comando toma config('telemetry.retention_days'), no env() en runtime.
        config(['telemetry.retention_days' => 1]);

        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);
        $base = ['device_id' => $device->id, 'site_id' => $site->id];
        Telemetry::create($base + ['ts' => now()->subDays(2), 'client_seq' => 1, 'occupancy' => 5]);
        Telemetry::create($base + ['ts' => now()->subHour(), 'client_seq' => 2, 'occupancy' => 3]);

        $this->artisan('telemetry:purge')->assertExitCode(0); // sin --days -> retencion 1 dia (config)

        $this->assertSame(1, Telemetry::count());
        $this->assertDatabaseMissing('telemetry', ['client_seq' => 1]);
    }

    public function test_purga_esta_programada_diariamente(): void
    {
        $schedule = app(Schedule::class);
        $found = collect($schedule->events())
            ->contains(fn ($e) => str_contains((string) ($e->command ?? ''), 'telemetry:purge'));

        $this->assertTrue($found, 'telemetry:purge deberia estar en el scheduler');
    }
}
