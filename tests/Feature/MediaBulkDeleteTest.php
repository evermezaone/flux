<?php

namespace Tests\Feature;

use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Device;
use App\Models\Media;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_delete_borra_los_media_seleccionados(): void
    {
        $user = User::factory()->create();
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);
        $m1 = Media::create(['device_id' => $device->id, 'site_id' => $site->id, 'tipo' => 'clip', 'file' => 'a.mp4']);
        $m2 = Media::create(['device_id' => $device->id, 'site_id' => $site->id, 'tipo' => 'clip', 'file' => 'b.mp4']);

        Livewire::actingAs($user)
            ->test(ListMedia::class)
            ->callTableBulkAction('delete', [$m1, $m2]);

        $this->assertDatabaseCount('media', 0);
    }

    public function test_borrar_media_elimina_el_archivo_fisico(): void
    {
        Storage::fake('local');
        $site = Site::create(['code' => 'ruta2_cruce1']);
        $device = Device::create([
            'site_id' => $site->id, 'code' => 'tel-01', 'device_key' => 'k', 'active' => true,
        ]);
        Storage::disk('local')->put('media/c.mp4', 'data');
        $media = Media::create(['device_id' => $device->id, 'site_id' => $site->id, 'tipo' => 'clip', 'file' => 'c.mp4']);

        $media->delete();

        Storage::disk('local')->assertMissing('media/c.mp4'); // REQ-0019
        $this->assertDatabaseCount('media', 0);
    }
}
