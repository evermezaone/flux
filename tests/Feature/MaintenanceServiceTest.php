<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use App\Services\MaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0043: estado energetico (umbrales) + mantenimiento preventivo (edad + recomendacion).
 */
class MaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function device(array $attrs = []): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create(array_merge([
            'site_id' => $site->id, 'code' => 'tel-mnt', 'device_key' => 'k-mnt', 'active' => true,
        ], $attrs));
    }

    private function withBattery(Device $d, int $pct, $temp = null): Device
    {
        $m = ['battery_pct' => $pct];
        if ($temp !== null) {
            $m['temp_c'] = $temp;
        }
        $h = new DeviceHealth;
        $h->forceFill(['device_id' => $d->id, 'overall' => 'ok', 'device_metrics' => $m, 'reported_at' => now()])->save();

        return $d->fresh();
    }

    public function test_niveles_de_bateria_por_umbral(): void
    {
        $svc = app(MaintenanceService::class);

        $ok = $svc->energyState($this->withBattery($this->device(), 80));
        $this->assertSame('ok', $ok['level']);
        $this->assertFalse($ok['alert']);

        // FLX-0043 (Codex): el usuario pidio alerta DESDE 50% -> warning ya genera alert=true.
        $warn = $svc->energyState($this->withBattery($this->device(['code' => 'a']), 50));
        $this->assertSame('warning', $warn['level']);
        $this->assertTrue($warn['alert']);

        $this->assertSame('critical', $svc->energyState($this->withBattery($this->device(['code' => 'b']), 25))['level']);

        $sd = $svc->energyState($this->withBattery($this->device(['code' => 'c']), 10));
        $this->assertSame('shutdown', $sd['level']);
        $this->assertTrue($sd['alert']);
    }

    public function test_severidad_de_columna_por_estado(): void
    {
        // FLX-0043 (Codex): la columna colorea por severidad (alerta visible sin leer el texto).
        $crit = \App\Models\DeviceHealth::where('device_id', $this->withBattery($this->device(), 12)->id)->first();
        $this->assertSame('danger', \App\Filament\Resources\DeviceHealthResource\Tables\DeviceHealthTable::maintenanceSeverity($crit));

        $warn = \App\Models\DeviceHealth::where('device_id', $this->withBattery($this->device(['code' => 'w']), 50)->id)->first();
        $this->assertSame('warning', \App\Filament\Resources\DeviceHealthResource\Tables\DeviceHealthTable::maintenanceSeverity($warn));

        $okH = \App\Models\DeviceHealth::where('device_id', $this->withBattery($this->device(['code' => 'o']), 90)->id)->first();
        $this->assertSame('gray', \App\Filament\Resources\DeviceHealthResource\Tables\DeviceHealthTable::maintenanceSeverity($okH));
    }

    public function test_umbrales_configurables_por_device(): void
    {
        config()->set('energy.battery_critical_pct', 30);
        $d = $this->withBattery($this->device(), 40);
        // Por device, subir el critico a 50 -> 40% pasa a critical.
        \App\Models\DeviceSetting::create(['device_id' => $d->id, 'key' => 'battery_critical_pct', 'value' => '50']);

        $this->assertSame('critical', app(MaintenanceService::class)->energyState($d->fresh())['level']);
    }

    public function test_temp_alta_genera_alerta(): void
    {
        config()->set('energy.temp_max_c', 50);
        $d = $this->withBattery($this->device(), 90, 55);
        $e = app(MaintenanceService::class)->energyState($d);
        $this->assertTrue($e['temp_alert']);
        $this->assertTrue($e['alert']);
    }

    public function test_edad_y_recomendacion(): void
    {
        config()->set('energy.review_months', 12);
        config()->set('energy.replace_months', 36);
        $svc = app(MaintenanceService::class);

        $nuevo = $this->device(['install_date' => now()->subMonths(2)]);
        $this->assertSame('ok', $svc->recommendation($nuevo)['status']);

        $revision = $this->device(['code' => 'r', 'install_date' => now()->subMonths(18)]);
        $this->assertSame('revision', $svc->recommendation($revision)['status']);

        $reemplazo = $this->device(['code' => 'x', 'install_date' => now()->subMonths(40)]);
        $rec = $svc->recommendation($reemplazo);
        $this->assertSame('reemplazo', $rec['status']);
        $this->assertSame(40, $rec['age_months']);
    }

    public function test_sin_fecha_de_instalacion(): void
    {
        $this->assertSame('sin_fecha', app(MaintenanceService::class)->recommendation($this->device())['status']);
    }
}
