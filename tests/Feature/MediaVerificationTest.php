<?php

namespace Tests\Feature;

use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Device;
use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MediaVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function media(): Media
    {
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);

        return Media::create([
            'device_id' => $device->id, 'site_id' => $site->id,
            'tipo' => 'clip', 'file' => '20260619_1830.mp4',
            'ts_start' => now()->subHour(), 'available' => false,
        ]);
    }

    public function test_recurso_media_carga_para_operador(): void
    {
        $this->media();
        $this->actingAs(User::factory()->create())
            ->get('/admin/media')
            ->assertOk();
    }

    public function test_pedir_clip_encola_publish_clip(): void
    {
        $media = $this->media();

        Livewire::actingAs(User::factory()->create())
            ->test(ListMedia::class)
            ->callTableAction('pedir_clip', $media);

        $this->assertDatabaseHas('commands', [
            'device_id' => $media->device_id,
            'cmd' => 'publish_clip',
            'status' => 'pending',
        ]);
    }

    public function test_pedir_clip_oculto_si_no_hay_ts_start(): void
    {
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);
        // timelapse sin ts_start: publish_clip seria invalido (REQ-0004 exige ts).
        $media = Media::create([
            'device_id' => $device->id, 'site_id' => $site->id,
            'tipo' => 'timelapse', 'file' => 'tl.mp4', 'ts_start' => null, 'available' => false,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(ListMedia::class)
            ->assertTableActionHidden('pedir_clip', $media);

        $this->assertDatabaseMissing('commands', ['cmd' => 'publish_clip']);
    }
}
