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
