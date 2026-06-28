<?php

namespace Tests\Feature;

use App\Filament\Widgets\NodesAlarmWidget;
use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0060: el widget de alarma del dashboard marca los nodos que no funcionan bien.
 */
class NodesAlarmWidgetTest extends TestCase
{
    use RefreshDatabase;

    /** Invoca getViewData (protected) del widget. */
    private function viewData(): array
    {
        $w = new NodesAlarmWidget();
        $m = new \ReflectionMethod($w, 'getViewData');
        $m->setAccessible(true);

        return $m->invoke($w);
    }

    private function device(string $code, string $key): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create(['site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true]);
    }

    public function test_nodo_sano_no_entra_en_alarma(): void
    {
        $d = $this->device('tel-ok', 'k-ok');
        DeviceHealth::create([
            'device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now(),
            'device_metrics' => ['detection' => ['no_reading' => false], 'requires_intervention' => false],
        ]);

        $this->assertSame(0, $this->viewData()['count']);
    }

    public function test_camara_sin_lectura_dispara_alarma(): void
    {
        $d = $this->device('tel-cam', 'k-cam');
        DeviceHealth::create([
            'device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now(),
            'device_metrics' => ['detection' => ['no_reading' => true]],
        ]);

        $data = $this->viewData();
        $this->assertSame(1, $data['count']);
        $this->assertSame('tel-cam', $data['nodes'][0]['code']);
        $this->assertContains('Cámara sin lectura', $data['nodes'][0]['reasons']);
    }

    public function test_offline_tiene_prioridad_sobre_requires_intervention(): void
    {
        $off = $this->device('tel-off', 'k-off');
        DeviceHealth::create([
            'device_id' => $off->id, 'overall' => 'ok', 'reported_at' => now()->subMinutes(60), 'device_metrics' => [],
        ]);
        $int = $this->device('tel-int', 'k-int');
        DeviceHealth::create([
            'device_id' => $int->id, 'overall' => 'ok', 'reported_at' => now(),
            'device_metrics' => ['requires_intervention' => true],
        ]);

        $data = $this->viewData();
        $this->assertSame(2, $data['count']);
        $this->assertSame('tel-off', $data['nodes'][0]['code']); // mayor severidad primero
        $this->assertContains('Sin latido (offline)', $data['nodes'][0]['reasons']);
    }

    public function test_device_sin_health_se_considera_offline(): void
    {
        $this->device('tel-nh', 'k-nh'); // sin DeviceHealth

        $data = $this->viewData();
        $this->assertSame(1, $data['count']);
        $this->assertContains('Sin latido (offline)', $data['nodes'][0]['reasons']);
    }

    public function test_dashboard_renderiza_la_alarma(): void
    {
        $user = \App\Models\User::factory()->create();
        $d = $this->device('tel-cam', 'k-cam');
        DeviceHealth::create([
            'device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now(),
            'device_metrics' => ['detection' => ['no_reading' => true]],
        ]);

        \Livewire\Livewire::actingAs($user)->test(NodesAlarmWidget::class)
            ->assertOk()
            ->assertSee('en alarma')
            ->assertSee('Cámara sin lectura')
            ->assertSee('tel-cam');
    }
}
