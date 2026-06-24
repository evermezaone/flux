<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\DeviceStabilityState;
use App\Models\Site;
use App\Services\CommandDispatcher;
use App\Services\StabilityRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0048/0050: supervisor de recuperacion (escalado + guards + opt-in).
 */
class StabilityRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{cmd:string, params:array}> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['stability.recovery.enabled' => true, 'stability.recovery.action_cooldown_s' => 90]);
        $fake = \Mockery::mock(CommandDispatcher::class);
        $fake->shouldReceive('dispatch')->andReturnUsing(function ($device, $cmd, $params, $channel) {
            $this->dispatched[] = ['cmd' => $cmd, 'params' => $params];

            return ['command_id' => count($this->dispatched), 'pushed' => true];
        });
        $this->app->instance(CommandDispatcher::class, $fake);
    }

    private function device(array $state = [], array $metrics = []): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);
        $d = Device::create(['site_id' => $site->id, 'code' => 'tel-'.uniqid(), 'device_key' => 'k'.uniqid(), 'active' => true]);
        DeviceStabilityState::create(array_merge(['device_id' => $d->id, 'stability_status' => 'critical'], $state));
        DeviceHealth::updateOrCreate(['device_id' => $d->id], ['overall' => 'fail', 'device_metrics' => $metrics, 'reported_at' => now()]);

        return $d->fresh(['stabilityState', 'health']);
    }

    private function svc(): StabilityRecovery
    {
        return app(StabilityRecovery::class);
    }

    public function test_opt_in_off_no_actua(): void
    {
        config(['stability.recovery.enabled' => false]);
        $d = $this->device();
        $this->svc()->tick($d);
        $this->assertEmpty($this->dispatched);
    }

    public function test_primer_paso_pide_diagnostico(): void
    {
        $d = $this->device();
        $st = $this->svc()->tick($d);
        $this->assertSame('get_diagnostics', $this->dispatched[0]['cmd']);
        $this->assertSame('diagnostics', $st->recovery_step);
    }

    public function test_segundo_paso_reinicia_app(): void
    {
        $d = $this->device(['recovery_step' => 'diagnostics', 'recovery_started_at' => now()->subMinutes(5), 'last_recovery_action_at' => now()->subMinutes(5)]);
        $st = $this->svc()->tick($d);
        $this->assertSame('restart', $st->recovery_step);
        $this->assertSame('restart', $this->dispatched[0]['cmd']);
        $this->assertSame('app', $this->dispatched[0]['params']['level']);
    }

    public function test_no_actua_durante_ota(): void
    {
        $d = $this->device([], ['update' => ['update_in_progress' => true]]);
        $st = $this->svc()->tick($d);
        $this->assertEmpty($this->dispatched); // no se reinicia durante OTA
        $this->assertSame('esperando_ota', $st->last_recovery_action);
    }

    public function test_escala_a_reboot_con_reboot_available(): void
    {
        // Codex R1: tras el paso restart, si device_owner.reboot_available=true -> restart(level=device).
        $d = $this->device(
            ['recovery_step' => 'restart', 'recovery_started_at' => now()->subMinutes(5), 'last_recovery_action_at' => now()->subMinutes(5)],
            ['device_owner' => ['reboot_available' => true]],
        );
        $st = $this->svc()->tick($d);
        $this->assertSame('reboot', $st->recovery_step);
        $this->assertSame('restart', $this->dispatched[0]['cmd']);
        $this->assertSame('device', $this->dispatched[0]['params']['level']);
    }

    public function test_relaunch_loop_no_reinicia_app(): void
    {
        // FLX-0050: en relaunch loop NO mandar restart_app (realimenta el loop); queda en hold.
        $d = $this->device(['recovery_step' => 'diagnostics', 'last_stability_event' => 'activity_relaunch_loop',
            'recovery_started_at' => now()->subMinutes(5), 'last_recovery_action_at' => now()->subMinutes(5)]);
        $st = $this->svc()->tick($d);
        $this->assertSame('hold', $st->recovery_step);
        $this->assertEmpty($this->dispatched); // no restart
    }

    public function test_sentinel_hibernacion_pide_diagnostico(): void
    {
        // FLX-0050: equipo critical con sentinel_oem_hibernation_suspected (formato PLANO que envia VLS) ->
        // el supervisor actua desde central (no asume watchdog sano) pidiendo get_diagnostics primero.
        $d = $this->device([], ['sentinel_oem_hibernation_suspected' => true, 'device_owner' => ['reboot_available' => true]]);
        $st = $this->svc()->tick($d);
        $this->assertSame('get_diagnostics', $this->dispatched[0]['cmd']);
        $this->assertSame('diagnostics', $st->recovery_step);
    }

    public function test_recuperado_vuelve_a_idle(): void
    {
        $d = $this->device(['stability_status' => 'ok', 'recovery_step' => 'restart']);
        $st = $this->svc()->tick($d);
        $this->assertSame('idle', $st->recovery_step);
    }
}
