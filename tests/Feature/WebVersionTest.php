<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Versionamiento del panel/web FLX (FLX REQ-0017): endpoint publico GET /api/v1/version y
 * version visible en el panel.
 */
class WebVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_version_es_publico_y_devuelve_la_web_version(): void
    {
        config()->set('version.web', '9.9.9');

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertExactJson(['app' => 'FLX', 'web_version' => '9.9.9']);
    }

    public function test_default_web_version_definida(): void
    {
        $this->assertNotEmpty(config('version.web'));
        $this->getJson('/api/v1/version')->assertOk()->assertJsonStructure(['app', 'web_version']);
    }

    public function test_panel_muestra_la_version_en_el_footer(): void
    {
        config()->set('version.web', '9.9.9');

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('FLUX v9.9.9');
    }

    public function test_panel_no_registra_el_widget_de_version_de_filament(): void
    {
        // El FilamentInfoWidget mostraba la version del framework (confuso). Ya no debe estar.
        $panel = \Filament\Facades\Filament::getPanel('admin');
        $this->assertNotContains(
            \Filament\Widgets\FilamentInfoWidget::class,
            $panel->getWidgets(),
        );
    }
}
