<?php

namespace Tests\Feature;

use App\Models\Command;
use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use App\Services\Fcm\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Canal de comando (FLX-0035 / VLS-0043): elegir por dónde se envía cada comando (auto|fcm|poll),
 * evitar doble ejecución y ver por dónde se ejecutó.
 */
class CommandChannelTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-ch', string $code = 'tel-ch', ?string $token = null): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);
        $d = Device::create(['site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true]);
        if ($token) {
            $d->forceFill(['fcm_token' => $token])->save();
        }

        return $d;
    }

    public function test_canal_poll_no_empuja_y_queda_en_cola(): void
    {
        $user = User::factory()->create();
        $d = $this->device(token: 'tok-poll');

        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldNotReceive('send'); // poll => sin push
        $this->app->instance(FcmSender::class, $mock);

        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code, 'cmd' => 'clear_recovery', 'channel' => 'poll',
        ])->assertSuccessful()->assertJsonPath('channel', 'poll')->assertJsonPath('pushed', false);

        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'clear_recovery', 'channel' => 'poll']);
    }

    public function test_canal_fcm_empuja(): void
    {
        $user = User::factory()->create();
        $d = $this->device(token: 'tok-fcm');

        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldReceive('send')->once()->andReturn(true);
        $this->app->instance(FcmSender::class, $mock);

        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code, 'cmd' => 'restart', 'params' => ['level' => 'app'], 'channel' => 'fcm',
        ])->assertSuccessful()->assertJsonPath('channel', 'fcm')->assertJsonPath('pushed', true);
    }

    public function test_pull_no_entrega_comandos_canal_fcm(): void
    {
        $d = $this->device(key: 'k-pull');
        // un comando 'fcm' (no debe entregarse por polling) y uno 'poll' (sí)
        Command::create(['device_id' => $d->id, 'cmd' => 'snapshot', 'channel' => 'fcm', 'status' => 'pending']);
        $poll = Command::create(['device_id' => $d->id, 'cmd' => 'clear_recovery', 'channel' => 'poll', 'status' => 'pending']);

        $res = $this->getJson('/api/v1/commands', ['X-Device-Key' => 'k-pull'])->assertOk()->json('commands');

        $ids = array_column($res, 'id');
        $this->assertContains($poll->id, $ids);
        $this->assertCount(1, $res); // solo el 'poll', no el 'fcm'
    }

    public function test_restart_device_se_fuerza_a_fcm(): void
    {
        // FLX-0040: el reinicio de telefono (level=device) se fuerza a FCM aunque se pida poll/auto,
        // para no quedar en cola y re-ejecutarse tras el reboot.
        $user = User::factory()->create();
        $d = $this->device(token: 'tok-dev');

        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldReceive('send')->once()->andReturn(true); // se empuja por FCM
        $this->app->instance(FcmSender::class, $mock);

        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code, 'cmd' => 'restart', 'params' => ['level' => 'device'], 'channel' => 'poll',
        ])->assertSuccessful()->assertJsonPath('channel', 'fcm')->assertJsonPath('pushed', true);

        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'restart', 'channel' => 'fcm']);
    }

    public function test_restart_device_fcm_no_se_entrega_por_polling(): void
    {
        // El restart device (forzado fcm) NO sale en el pull -> no se re-ejecuta post-reboot.
        $d = $this->device(key: 'k-dev2', code: 'tel-dev2', token: 'tok-dev2');
        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldReceive('send')->andReturn(true);
        $this->app->instance(FcmSender::class, $mock);

        app(\App\Services\CommandDispatcher::class)->dispatch($d, 'restart', ['level' => 'device'], 'auto');

        $res = $this->getJson('/api/v1/commands', ['X-Device-Key' => 'k-dev2'])->assertOk()->json('commands');
        $this->assertCount(0, $res); // fcm puro: no se entrega por polling
    }

    public function test_restart_app_respeta_el_canal_elegido(): void
    {
        // Compatibilidad: restart app/service NO se fuerza; respeta el canal pedido (poll).
        $user = User::factory()->create();
        $d = $this->device(key: 'k-app', code: 'tel-app');

        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code, 'cmd' => 'restart', 'params' => ['level' => 'app'], 'channel' => 'poll',
        ])->assertSuccessful()->assertJsonPath('channel', 'poll');

        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'restart', 'channel' => 'poll']);
    }

    public function test_diagnosticos_industriales_son_comandos_validos(): void
    {
        // FLX-0042: los get_* de diagnostico extendido se aceptan y encolan.
        $user = User::factory()->create();
        $d = $this->device(key: 'k-diag', code: 'tel-diag');

        foreach (['get_status', 'get_apps', 'get_permissions', 'get_device_policy', 'get_battery', 'get_network', 'get_foreground_state'] as $cmd) {
            $this->actingAs($user)->postJson('/api/v1/commands', [
                'device' => $d->code, 'cmd' => $cmd, 'channel' => 'poll',
            ])->assertSuccessful();
            $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => $cmd, 'channel' => 'poll']);
        }
    }

    public function test_stop_all_se_encola(): void
    {
        // VLS-0052/FLX-0038: el kill-switch 'stop_all' es un comando valido (sin params) y se encola.
        $user = User::factory()->create();
        $d = $this->device(key: 'k-stop', code: 'tel-stop');

        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code, 'cmd' => 'stop_all', 'channel' => 'poll',
        ])->assertSuccessful()->assertJsonPath('channel', 'poll');

        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'stop_all', 'channel' => 'poll']);
    }

    public function test_resume_se_fuerza_a_fcm(): void
    {
        // FLX-0053/VLS-0084: 'resume' (contraparte de stop_all) se fuerza a FCM aunque se pida poll/auto,
        // porque un equipo detenido NO consulta la cola; FCM si arranca el proceso.
        $user = User::factory()->create();
        $d = $this->device(key: 'k-res', code: 'tel-res', token: 'tok-res');

        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldReceive('send')->once()->andReturn(true);
        $this->app->instance(FcmSender::class, $mock);

        $this->actingAs($user)->postJson('/api/v1/commands', [
            'device' => $d->code, 'cmd' => 'resume', 'channel' => 'poll',
        ])->assertSuccessful()->assertJsonPath('channel', 'fcm')->assertJsonPath('pushed', true);

        $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => 'resume', 'channel' => 'fcm']);
    }

    public function test_resume_fcm_no_se_entrega_por_polling(): void
    {
        // 'resume' forzado fcm: NO sale en el pull (un equipo detenido no consulta la cola; llega por push).
        $d = $this->device(key: 'k-res2', code: 'tel-res2', token: 'tok-res2');
        $mock = Mockery::mock(FcmSender::class);
        $mock->shouldReceive('send')->andReturn(true);
        $this->app->instance(FcmSender::class, $mock);

        app(\App\Services\CommandDispatcher::class)->dispatch($d, 'resume', [], 'auto');

        $res = $this->getJson('/api/v1/commands', ['X-Device-Key' => 'k-res2'])->assertOk()->json('commands');
        $this->assertCount(0, $res);
    }

    public function test_ack_guarda_exec_channel(): void
    {
        $d = $this->device(key: 'k-ack');
        $cmd = Command::create(['device_id' => $d->id, 'cmd' => 'restart', 'channel' => 'auto', 'status' => 'sent']);

        $this->postJson("/api/v1/commands/{$cmd->id}/ack",
            ['status' => 'done', 'result' => 'ok', 'exec_channel' => 'fcm'],
            ['X-Device-Key' => 'k-ack'])->assertOk();

        $this->assertDatabaseHas('commands', ['id' => $cmd->id, 'status' => 'done', 'exec_channel' => 'fcm']);
    }

    public function test_ack_idempotente_si_ya_done(): void
    {
        // Anti doble ejecución: si el comando ya está 'done', un segundo ack no lo re-procesa.
        $d = $this->device(key: 'k-idem');
        $cmd = Command::create(['device_id' => $d->id, 'cmd' => 'restart', 'channel' => 'auto', 'status' => 'done', 'exec_channel' => 'fcm']);

        $this->postJson("/api/v1/commands/{$cmd->id}/ack",
            ['status' => 'failed', 'result' => 'tarde', 'exec_channel' => 'poll'],
            ['X-Device-Key' => 'k-idem'])->assertOk()->assertJsonPath('already', true);

        // No cambió: sigue done / fcm.
        $this->assertDatabaseHas('commands', ['id' => $cmd->id, 'status' => 'done', 'exec_channel' => 'fcm']);
    }
}
