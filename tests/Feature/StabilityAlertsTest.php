<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceStabilityState;
use App\Models\Site;
use App\Notifications\StabilityAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * FLX-0047 R1: alertas de estabilidad (anti-tormenta + escalado + recuperacion).
 */
class StabilityAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function deviceWithStatus(string $status, ?string $alerted = null): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);
        $d = Device::create(['site_id' => $site->id, 'code' => 'tel-'.$status.($alerted ?? 'n'), 'device_key' => 'k'.uniqid(), 'active' => true]);
        DeviceStabilityState::create(['device_id' => $d->id, 'stability_status' => $status, 'alerted_status' => $alerted]);

        return $d;
    }

    private function runAlerts(): void
    {
        config(['stability.alert_email' => 'ops@example.com']);
        $this->artisan('stability:check-alerts')->assertSuccessful();
    }

    public function test_entrada_a_warn_alerta(): void
    {
        Notification::fake();
        $d = $this->deviceWithStatus('warn', null);
        $this->runAlerts();
        Notification::assertSentOnDemand(StabilityAlert::class);
        $this->assertSame('warn', $d->stabilityState->fresh()->alerted_status);
    }

    public function test_mismo_nivel_no_re_alerta(): void
    {
        // FLX-0047 R1: ya avisado en warn y sigue warn -> NO re-alerta (anti-tormenta).
        Notification::fake();
        $this->deviceWithStatus('warn', 'warn');
        $this->runAlerts();
        Notification::assertNothingSent();
    }

    public function test_escalado_warn_a_critical_re_alerta(): void
    {
        Notification::fake();
        $d = $this->deviceWithStatus('critical', 'warn');
        $this->runAlerts();
        Notification::assertSentOnDemand(StabilityAlert::class);
        $this->assertSame('critical', $d->stabilityState->fresh()->alerted_status);
    }

    public function test_recuperacion_alerta_y_limpia(): void
    {
        Notification::fake();
        $d = $this->deviceWithStatus('ok', 'critical');
        $this->runAlerts();
        Notification::assertSentOnDemand(StabilityAlert::class, fn ($n) => $n->recovered === true);
        $this->assertNull($d->stabilityState->fresh()->alerted_status);
    }

    public function test_ok_sin_historial_no_alerta(): void
    {
        Notification::fake();
        $this->deviceWithStatus('ok', null);
        $this->runAlerts();
        Notification::assertNothingSent();
    }
}
