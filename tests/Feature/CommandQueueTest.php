<?php

namespace Tests\Feature;

use App\Models\Command;
use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandQueueTest extends TestCase
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

    public function test_enqueue_requiere_operador(): void
    {
        $this->device();
        $this->postJson('/api/v1/commands', ['device' => 'tel-01', 'cmd' => 'snapshot'])
            ->assertStatus(401);
    }

    public function test_operador_encola_comando_pending(): void
    {
        $this->device();
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/commands', ['device' => 'tel-01', 'cmd' => 'snapshot'])
            ->assertCreated()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('commands', ['cmd' => 'snapshot', 'status' => 'pending']);
    }

    public function test_enqueue_valida_cmd_y_params(): void
    {
        $this->device();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/commands', ['device' => 'tel-01', 'cmd' => 'invalido'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/api/v1/commands', ['device' => 'tel-01', 'cmd' => 'publish_clip'])
            ->assertStatus(422); // falta params.ts
    }

    public function test_device_pull_marca_sent(): void
    {
        $d = $this->device();
        Command::create(['device_id' => $d->id, 'cmd' => 'snapshot', 'status' => 'pending']);

        $this->getJson('/api/v1/commands', ['X-Device-Key' => 'k-123'])
            ->assertOk()
            ->assertJsonPath('commands.0.cmd', 'snapshot');

        $this->assertDatabaseHas('commands', ['cmd' => 'snapshot', 'status' => 'sent']);
        $this->assertNotNull(Command::first()->picked_at);

        // segundo pull: ya no hay pendientes
        $this->getJson('/api/v1/commands', ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJsonCount(0, 'commands');
    }

    public function test_device_ack_marca_done(): void
    {
        $d = $this->device();
        $cmd = Command::create(['device_id' => $d->id, 'cmd' => 'snapshot', 'status' => 'sent']);

        $this->postJson("/api/v1/commands/{$cmd->id}/ack", ['status' => 'done'], ['X-Device-Key' => 'k-123'])
            ->assertOk();

        $cmd->refresh();
        $this->assertSame('done', $cmd->status);
        $this->assertNotNull($cmd->done_at);
    }

    public function test_no_se_puede_ack_comando_de_otro_device(): void
    {
        $d1 = $this->device('k-1', 'tel-1');
        $d2 = $this->device('k-2', 'tel-2');
        $cmd = Command::create(['device_id' => $d2->id, 'cmd' => 'snapshot', 'status' => 'sent']);

        $this->postJson("/api/v1/commands/{$cmd->id}/ack", ['status' => 'done'], ['X-Device-Key' => 'k-1'])
            ->assertStatus(403);
    }
}
