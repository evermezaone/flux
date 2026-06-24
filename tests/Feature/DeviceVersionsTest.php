<?php

namespace Tests\Feature;

use App\Filament\Resources\DeviceHealthResource\Tables\DeviceHealthTable;
use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0052: visibilidad de versiones VLS/Sentinel. Verifica el veredicto (al dia / actualizar VLS / Sentinel /
 * ambos / Sentinel no instalado / desconocido) leyendo el bloque `apps` (VLS-0078) de device_metrics, comparado
 * contra la version mas nueva vista en la flota.
 */
class DeviceVersionsTest extends TestCase
{
    use RefreshDatabase;

    private function apps(int $vlsCode, int $senCode, bool $senInstalled = true, ?string $senName = null): array
    {
        return [
            'vls' => ['package' => 'com.vialsense', 'version_code' => $vlsCode, 'version_name' => "v{$vlsCode}", 'installed' => true],
            'sentinel' => $senInstalled
                ? ['package' => 'com.vialsense.sentinel', 'version_code' => $senCode, 'version_name' => $senName ?? "v{$senCode}", 'installed' => true]
                : ['package' => 'com.vialsense.sentinel', 'installed' => false],
        ];
    }

    private function device(array $metrics): DeviceHealth
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);
        $d = Device::create(['site_id' => $site->id, 'code' => 'tel-'.uniqid(), 'device_key' => 'k'.uniqid(), 'active' => true]);
        DeviceHealth::updateOrCreate(['device_id' => $d->id], ['overall' => 'ok', 'device_metrics' => $metrics, 'reported_at' => now()]);

        return $d->fresh('health')->health;
    }

    private function verdict(DeviceHealth $h): string
    {
        $ref = new \ReflectionClass(DeviceHealthTable::class);
        $cache = $ref->getProperty('fleetMaxCache');
        $cache->setAccessible(true);
        $cache->setValue(null, null); // recalcular el max de flota desde la BD actual
        $m = $ref->getMethod('versionsVerdict');
        $m->setAccessible(true);

        return $m->invoke(null, $h);
    }

    private function text(DeviceHealth $h): string
    {
        $ref = new \ReflectionClass(DeviceHealthTable::class);
        $cache = $ref->getProperty('fleetMaxCache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
        $m = $ref->getMethod('versions');
        $m->setAccessible(true);

        return $m->invoke(null, $h);
    }

    public function test_al_dia_cuando_es_la_version_mas_nueva(): void
    {
        $newest = $this->device(['apps' => $this->apps(100, 100)]);
        $this->assertSame('ok', $this->verdict($newest));
        $this->assertStringContainsString('al día', $this->text($newest));
    }

    public function test_actualizar_vls(): void
    {
        $this->device(['apps' => $this->apps(100, 100)]);       // flota: max vls=100
        $old = $this->device(['apps' => $this->apps(90, 100)]);  // este: vls viejo
        $this->assertSame('vls_old', $this->verdict($old));
    }

    public function test_actualizar_sentinel(): void
    {
        $this->device(['apps' => $this->apps(100, 100)]);
        $old = $this->device(['apps' => $this->apps(100, 90)]);
        $this->assertSame('sentinel_old', $this->verdict($old));
    }

    public function test_actualizar_ambos(): void
    {
        $this->device(['apps' => $this->apps(100, 100)]);
        $old = $this->device(['apps' => $this->apps(90, 90)]);
        $this->assertSame('both_old', $this->verdict($old));
    }

    public function test_sentinel_legacy_1_0_es_desactualizado(): void
    {
        $this->device(['apps' => $this->apps(100, 100)]);
        $legacy = $this->device(['apps' => $this->apps(100, 1, true, '1.0')]);
        $this->assertSame('sentinel_old', $this->verdict($legacy));
    }

    public function test_sentinel_no_instalado(): void
    {
        $h = $this->device(['apps' => $this->apps(100, 0, false)]);
        $this->assertSame('sentinel_missing', $this->verdict($h));
    }

    public function test_provider_caido_anidado(): void
    {
        $h = $this->device(['apps' => $this->apps(100, 100), 'sentinel' => ['provider_ok' => false]]);
        $this->assertSame('sentinel_provider_down', $this->verdict($h));
    }

    public function test_heartbeat_viejo_sin_apps_no_rompe(): void
    {
        $h = $this->device(['app_foreground' => true]); // sin bloque apps
        $this->assertSame('unknown', $this->verdict($h));
        $this->assertStringContainsString('desconocido', $this->text($h));
    }
}
