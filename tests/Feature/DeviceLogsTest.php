<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FLX-0039: logs remotos del equipo. get_logs/reset_logs validos; upload por X-Device-Key guarda archivo
 * privado + metadata; descarga solo autenticada.
 */
class DeviceLogsTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-logs', string $code = 'tel-logs'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create(['site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true]);
    }

    public function test_get_logs_y_reset_logs_son_comandos_validos(): void
    {
        $user = User::factory()->create();
        $d = $this->device();

        foreach (['get_logs', 'reset_logs'] as $cmd) {
            $this->actingAs($user)->postJson('/api/v1/commands', [
                'device' => $d->code, 'cmd' => $cmd, 'channel' => 'poll',
            ])->assertSuccessful();
            $this->assertDatabaseHas('commands', ['device_id' => $d->id, 'cmd' => $cmd]);
        }
    }

    public function test_upload_guarda_archivo_privado_y_metadata(): void
    {
        Storage::fake('local');
        $d = $this->device(key: 'k-up');

        $file = UploadedFile::fake()->createWithContent('logs.jsonl', "{\"e\":\"boot\"}\n");

        $this->postJson('/api/v1/device-logs', [
            'file' => $file, 'source' => 'combined', 'summary' => 'fallo reboot', 'build' => '78085113',
        ], ['X-Device-Key' => 'k-up'])->assertOk()->assertJsonPath('ok', true);

        $log = DeviceLog::where('device_id', $d->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('combined', $log->source);
        $this->assertSame('fallo reboot', $log->summary);
        Storage::disk('local')->assertExists($log->path);
    }

    public function test_upload_rechaza_extension_no_permitida(): void
    {
        Storage::fake('local');
        $this->device(key: 'k-bad');

        $file = UploadedFile::fake()->create('malware.exe', 1);

        $this->postJson('/api/v1/device-logs', ['file' => $file], ['X-Device-Key' => 'k-bad'])
            ->assertStatus(422);
    }

    public function test_upload_requiere_device_key(): void
    {
        $file = UploadedFile::fake()->createWithContent('logs.txt', 'x');
        $this->postJson('/api/v1/device-logs', ['file' => $file])->assertStatus(401);
    }

    public function test_descarga_requiere_autenticacion(): void
    {
        Storage::fake('local');
        $d = $this->device(key: 'k-dl');
        Storage::disk('local')->put('device-logs/'.$d->id.'/x.jsonl', 'contenido');
        $log = DeviceLog::create([
            'device_id' => $d->id, 'source' => 'vls', 'path' => 'device-logs/'.$d->id.'/x.jsonl',
            'size' => 9, 'reported_at' => now(),
        ]);

        // Sin sesion -> bloqueado (401; no hay ruta 'login' nombrada -> el guard no redirige).
        $this->get("/api/v1/device-logs/{$log->id}/download")->assertStatus(401);

        // Con sesion -> descarga.
        $user = User::factory()->create();
        $this->actingAs($user)->get("/api/v1/device-logs/{$log->id}/download")->assertOk();
    }
}
