<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private function device(): Device
    {
        $site = Site::create(['code' => 'ruta2_cruce1']);

        return Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k-123', 'active' => true,
        ]);
    }

    public function test_upload_requiere_device_key(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('c.mp4', 512, 'video/mp4');

        $this->post('/api/v1/media/upload', ['file' => 'c.mp4', 'tipo' => 'clip', 'archivo' => $file])
            ->assertStatus(401);
    }

    public function test_device_sube_clip_y_setea_url(): void
    {
        Storage::fake('local');
        $this->device();
        $file = UploadedFile::fake()->create('20260619_1830.mp4', 2048, 'video/mp4');

        $this->post('/api/v1/media/upload', [
            'file' => '20260619_1830.mp4', 'tipo' => 'clip', 'archivo' => $file,
        ], ['X-Device-Key' => 'k-123'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Storage::disk('local')->assertExists('media/20260619_1830.mp4');
        $media = Media::first();
        $this->assertTrue((bool) $media->available);
        $this->assertNotNull($media->url);
        $this->assertSame('20260619_1830.mp4', $media->file);
    }

    public function test_upload_rechaza_tipo_no_permitido(): void
    {
        Storage::fake('local');
        $this->device();
        $file = UploadedFile::fake()->create('mal.exe', 10, 'application/octet-stream');

        $this->postJson('/api/v1/media/upload', [
            'file' => 'mal.exe', 'tipo' => 'clip', 'archivo' => $file,
        ], ['X-Device-Key' => 'k-123'])
            ->assertStatus(422);
    }

    public function test_descarga_requiere_operador(): void
    {
        Storage::fake('local');
        $this->device();
        $file = UploadedFile::fake()->create('c.mp4', 512, 'video/mp4');
        $this->post('/api/v1/media/upload', ['file' => 'c.mp4', 'tipo' => 'clip', 'archivo' => $file], ['X-Device-Key' => 'k-123']);
        $media = Media::first();

        // sin operador: la API responde 401 (el sobre JSON de api/* unifica auth -> 401)
        $this->getJson("/api/v1/media/{$media->id}/download")->assertStatus(401);
        // con operador
        $this->actingAs(User::factory()->create())
            ->get("/api/v1/media/{$media->id}/download")
            ->assertOk();
    }
}
