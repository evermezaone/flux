<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use App\Services\RequirementsMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0044: estado de prerequisitos operativos (counts + anti-tormenta + recuperacion).
 */
class RequirementsMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $code = 'tel-req'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create(['site_id' => $site->id, 'code' => $code, 'device_key' => 'k-'.$code, 'active' => true]);
    }

    private function report(Device $d, ?array $checks): Device
    {
        $metrics = $checks === null ? [] : ['operational_requirements' => ['checks' => $checks]];
        DeviceHealth::updateOrCreate(['device_id' => $d->id], ['overall' => 'ok', 'device_metrics' => $metrics, 'reported_at' => now()]);

        return $d->fresh('health');
    }

    public function test_detecta_criticos_y_warnings(): void
    {
        $d = $this->report($this->device(), [
            'app_foreground' => ['ok' => false, 'severity' => 'critical', 'detail' => 'en background'],
            'camera_permission' => ['ok' => false, 'severity' => 'critical'],
            'battery' => ['ok' => false, 'severity' => 'warning'],
            'sentinel' => ['ok' => true],
        ]);

        $st = app(RequirementsMonitor::class)->evaluate($d);

        $this->assertFalse($st->ok);
        $this->assertSame(2, $st->critical_count);
        $this->assertSame(1, $st->warning_count);
        $this->assertCount(3, $st->failures);
    }

    public function test_anti_tormenta_no_cambia_sin_cambio(): void
    {
        $checks = ['app_foreground' => ['ok' => false, 'severity' => 'critical']];
        $d = $this->report($this->device(), $checks);
        $svc = app(RequirementsMonitor::class);

        $first = $svc->evaluate($d);
        $changedAt = $first->last_changed_at;

        // Mismo estado en otro heartbeat -> last_changed_at NO cambia (no re-alerta).
        $second = $svc->evaluate($this->report($d, $checks));
        $this->assertEquals($changedAt->timestamp, $second->last_changed_at->timestamp);
    }

    public function test_cambio_de_fallos_actualiza_last_changed(): void
    {
        $d = $this->report($this->device(), ['app_foreground' => ['ok' => false, 'severity' => 'critical']]);
        $svc = app(RequirementsMonitor::class);
        $first = $svc->evaluate($d);

        // Cambia el conjunto de fallos -> last_changed_at se actualiza.
        $second = $svc->evaluate($this->report($d, [
            'app_foreground' => ['ok' => false, 'severity' => 'critical'],
            'camera_permission' => ['ok' => false, 'severity' => 'critical'],
        ]));

        $this->assertGreaterThanOrEqual($first->last_changed_at->timestamp, $second->last_changed_at->timestamp);
        $this->assertSame(2, $second->critical_count);
    }

    public function test_recuperacion(): void
    {
        $d = $this->report($this->device(), ['app_foreground' => ['ok' => false, 'severity' => 'critical']]);
        $svc = app(RequirementsMonitor::class);
        $svc->evaluate($d);

        $rec = $svc->evaluate($this->report($d, ['app_foreground' => ['ok' => true]]));
        $this->assertTrue($rec->ok);
        $this->assertNotNull($rec->last_recovery_at);
        $this->assertNull($rec->failing_since);
    }

    public function test_sin_bloque_devuelve_null(): void
    {
        $d = $this->report($this->device(), null);
        $this->assertNull(app(RequirementsMonitor::class)->evaluate($d));
    }
}
