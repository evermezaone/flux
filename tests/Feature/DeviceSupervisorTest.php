<?php

namespace Tests\Feature;

use App\Models\Command;
use App\Models\Device;
use App\Models\Site;
use App\Services\Fcm\FcmSender;
use App\Services\RemoteSupervisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * FLX-0041: supervisor remoto. Estados + escalado + anti-tormenta (cooldown/ventana) + opt-in.
 */
class DeviceSupervisorTest extends TestCase
{
    use RefreshDatabase;

    private function device(?string $token = 'tok-sup'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);
        $d = Device::create(['site_id' => $site->id, 'code' => 'tel-sup', 'device_key' => 'k-sup', 'active' => true]);
        if ($token) {
            $d->forceFill(['fcm_token' => $token])->save();
        }

        return $d->fresh();
    }

    private function supervisor(): RemoteSupervisor
    {
        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldReceive('send')->andReturn(true); // push best-effort
        $this->app->instance(FcmSender::class, $mock);

        return app(RemoteSupervisor::class);
    }

    private function stale(Device $d): Device
    {
        $d->forceFill(['last_seen_at' => now()->subSeconds(1000)])->save();

        return $d->fresh();
    }

    public function test_online_con_heartbeat_fresco(): void
    {
        $d = $this->device();
        $d->forceFill(['last_seen_at' => now()])->save();

        $sup = $this->supervisor()->tick($d->fresh());

        $this->assertSame('online', $sup->state);
    }

    public function test_sin_metricas_con_autoacciones_dispara_get_logs(): void
    {
        config()->set('supervisor.enabled', true);
        $d = $this->stale($this->device());

        $sup = $this->supervisor()->tick($d);

        $this->assertSame('recuperando', $sup->state);
        $this->assertSame('get_logs', $sup->last_action);
        $this->assertSame(1, $sup->step);
        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'get_logs']);
    }

    public function test_autoacciones_deshabilitadas_no_actua(): void
    {
        // supervisor.enabled = false por defecto -> solo reporta, no actua.
        $d = $this->stale($this->device());

        $sup = $this->supervisor()->tick($d);

        $this->assertSame('sin_metricas', $sup->state);
        $this->assertDatabaseMissing('commands', ['device_id' => $d->id, 'cmd' => 'get_logs']);
    }

    public function test_cooldown_no_genera_tormenta(): void
    {
        config()->set('supervisor.enabled', true);
        config()->set('supervisor.action_cooldown_s', 120);
        $d = $this->stale($this->device());
        $s = $this->supervisor();

        $s->tick($d);              // paso 0 -> get_logs (1 accion)
        $sup = $s->tick($d->fresh()); // dentro del cooldown -> sin nueva accion

        $this->assertSame('recuperando', $sup->state);
        $this->assertSame(1, $sup->window_count); // NO tormenta
        $this->assertSame(1, Command::where('device_id', $d->id)->where('cmd', 'get_logs')->count());
    }

    public function test_tope_por_ventana_marca_intervencion(): void
    {
        config()->set('supervisor.enabled', true);
        config()->set('supervisor.max_actions_per_window', 2);
        config()->set('supervisor.action_cooldown_s', 0); // sin cooldown -> escala en cada tick
        $d = $this->stale($this->device());
        $s = $this->supervisor();

        $s->tick($d);              // accion 1 (get_logs)
        $s->tick($d->fresh());     // accion 2 (ping)
        $sup = $s->tick($d->fresh()); // tope alcanzado

        $this->assertSame('requiere_intervencion', $sup->state);
        $this->assertSame(2, $sup->window_count);
    }

    public function test_escalado_avanza_get_logs_ping_restart(): void
    {
        config()->set('supervisor.enabled', true);
        config()->set('supervisor.max_actions_per_window', 10);
        config()->set('supervisor.action_cooldown_s', 0);
        $d = $this->stale($this->device());
        $s = $this->supervisor();

        $a = $s->tick($d);          // get_logs
        $b = $s->tick($d->fresh()); // ping
        $c = $s->tick($d->fresh()); // restart_app

        $this->assertSame('get_logs', $a->last_action);
        $this->assertSame('ping', $b->last_action);
        $this->assertSame('restart_app', $c->last_action);
        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'restart']);
    }
}
