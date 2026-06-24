<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use App\Services\SentinelSupervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0051: alertas criticas de Sentinel/permisos.
 */
class SentinelSupervisorTest extends TestCase
{
    use RefreshDatabase;

    private function device(array $metrics): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);
        $d = Device::create(['site_id' => $site->id, 'code' => 'tel-'.uniqid(), 'device_key' => 'k'.uniqid(), 'active' => true]);
        DeviceHealth::updateOrCreate(['device_id' => $d->id], ['overall' => 'ok', 'device_metrics' => $metrics, 'reported_at' => now()]);

        return $d->fresh('health');
    }

    public function test_sentinel_stale_es_critico(): void
    {
        $d = $this->device(['sentinel_watch_status' => 'down']);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertTrue($res['critical']);
        $this->assertDatabaseHas('stability_events', ['device_id' => $d->id, 'event_type' => 'sentinel_critical']);
        $this->assertSame('critical', $d->stabilityState()->first()->stability_status);
    }

    public function test_falta_notificaciones_sentinel(): void
    {
        $d = $this->device(['sentinel_watch_status' => 'ok', 'industrial_provisioning' => ['post_notifications_sentinel' => false]]);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertContains('Sentinel sin permiso de notificaciones', $res['reasons']);
    }

    public function test_background_sin_recovery(): void
    {
        $d = $this->device(['sentinel_watch_status' => 'ok', 'app_foreground' => false, 'sentinel_recovery_capable' => false]);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertTrue($res['critical']);
    }

    public function test_provider_ok_false_anidado(): void
    {
        // Codex R1: contrato anidado sentinel.provider_ok=false -> critico aunque no llegue watch_status=down.
        $d = $this->device(['sentinel' => ['provider_ok' => false]]);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertTrue($res['critical']);
        $this->assertContains('Sentinel provider sin responder', $res['reasons']);
    }

    public function test_service_running_false_anidado(): void
    {
        $d = $this->device(['sentinel' => ['service_running' => false]]);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertTrue($res['critical']);
        $this->assertContains('Sentinel sin servicio foreground', $res['reasons']);
    }

    public function test_log_age_stale(): void
    {
        config(['stability.sentinel_log_stale_s' => 300]);
        $d = $this->device(['sentinel' => ['log_age_s' => 900]]);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertTrue($res['critical']);
        // details guarda causa + snapshot.
        $ev = \App\Models\StabilityEvent::where('device_id', $d->id)->where('event_type', 'sentinel_critical')->first();
        $this->assertNotNull($ev->details['reasons'] ?? null);
    }

    public function test_process_seen_false(): void
    {
        // Codex R2: sentinel.process_seen=false -> critico (Sentinel sin proceso visible).
        $d = $this->device(['sentinel' => ['process_seen' => false]]);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertTrue($res['critical']);
        $this->assertContains('Sentinel sin proceso visible', $res['reasons']);
    }

    public function test_todo_ok_no_alerta(): void
    {
        $d = $this->device(['sentinel_watch_status' => 'ok', 'app_foreground' => true,
            'industrial_provisioning' => ['post_notifications_sentinel' => true, 'post_notifications_vls' => true]]);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertFalse($res['critical']);
        $this->assertDatabaseMissing('stability_events', ['device_id' => $d->id, 'event_type' => 'sentinel_critical']);
    }

    public function test_sin_metricas_no_rompe(): void
    {
        $d = $this->device([]);
        $res = app(SentinelSupervisor::class)->evaluate($d);
        $this->assertFalse($res['critical']);
    }

    public function test_idempotente_por_hora(): void
    {
        $d = $this->device(['sentinel_watch_status' => 'down']);
        $svc = app(SentinelSupervisor::class);
        $svc->evaluate($d);
        $svc->evaluate($d);
        $this->assertSame(1, \App\Models\StabilityEvent::where('device_id', $d->id)->where('event_type', 'sentinel_critical')->count());
    }
}
