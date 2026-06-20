<?php

namespace Tests\Feature;

use App\Models\Command;
use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trazabilidad de comandos (FLX REQ-0015): ciclo de vida (created/sent/done/failed) en
 * command_events + result del ack persistido en el comando.
 */
class CommandTraceTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-123', string $code = 'tel-01'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create([
            'site_id' => $site->id,
            'code' => $code,
            'device_key' => $key,
            'active' => true,
        ]);
    }

    public function test_enqueue_registra_evento_created(): void
    {
        $this->device();
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/commands', ['device' => 'tel-01', 'cmd' => 'snapshot'])
            ->assertCreated();

        $cmd = Command::first();
        $this->assertDatabaseHas('command_events', [
            'command_id' => $cmd->id,
            'event' => 'created',
        ]);
    }

    public function test_pull_registra_evento_sent(): void
    {
        $d = $this->device();
        $cmd = Command::create(['device_id' => $d->id, 'cmd' => 'snapshot', 'status' => 'pending']);

        $this->getJson('/api/v1/commands', ['X-Device-Key' => 'k-123'])->assertOk();

        $this->assertDatabaseHas('command_events', ['command_id' => $cmd->id, 'event' => 'sent']);
    }

    public function test_ack_con_result_persiste_y_registra_evento(): void
    {
        $d = $this->device();
        $cmd = Command::create(['device_id' => $d->id, 'cmd' => 'publish_clip', 'status' => 'sent']);

        $this->postJson("/api/v1/commands/{$cmd->id}/ack",
            ['status' => 'done', 'result' => 'clip grabado on-demand y subido'],
            ['X-Device-Key' => 'k-123'])
            ->assertOk();

        $cmd->refresh();
        $this->assertSame('done', $cmd->status);
        $this->assertSame('clip grabado on-demand y subido', $cmd->result);
        $this->assertDatabaseHas('command_events', [
            'command_id' => $cmd->id,
            'event' => 'done',
            'note' => 'clip grabado on-demand y subido',
        ]);
    }

    public function test_ack_failed_se_traza(): void
    {
        $d = $this->device();
        $cmd = Command::create(['device_id' => $d->id, 'cmd' => 'snapshot', 'status' => 'sent']);

        $this->postJson("/api/v1/commands/{$cmd->id}/ack",
            ['status' => 'failed', 'result' => 'sin permiso de camara'],
            ['X-Device-Key' => 'k-123'])
            ->assertOk();

        $this->assertDatabaseHas('command_events', [
            'command_id' => $cmd->id,
            'event' => 'failed',
            'note' => 'sin permiso de camara',
        ]);
    }

    public function test_ciclo_de_vida_completo_ordenado(): void
    {
        $d = $this->device();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/commands', ['device' => 'tel-01', 'cmd' => 'snapshot'])
            ->assertCreated();
        $cmd = Command::first();

        $this->getJson('/api/v1/commands', ['X-Device-Key' => 'k-123'])->assertOk();
        $this->postJson("/api/v1/commands/{$cmd->id}/ack", ['status' => 'done'], ['X-Device-Key' => 'k-123'])->assertOk();

        $events = $cmd->refresh()->events->pluck('event')->all();
        $this->assertSame(['created', 'sent', 'done'], $events);
    }
}
