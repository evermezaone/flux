<?php

namespace Tests\Feature;

use App\Filament\Resources\DeviceHealthResource\Pages\ListDeviceHealth;
use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use App\Models\User;
use App\Notifications\DeviceHealthAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Monitor de salud de equipos (FLX REQ-0026): heartbeat, healthz, semaforo y alertas.
 */
class HealthMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-h', string $code = 'tel-01'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create([
            'site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true,
        ]);
    }

    public function test_health_requiere_device_key(): void
    {
        $this->postJson('/api/v1/health', ['overall' => 'ok'])->assertStatus(401);
    }

    public function test_health_persiste_estado_y_actualiza_last_seen(): void
    {
        $d = $this->device();

        $this->postJson('/api/v1/health', [
            'overall' => 'warn',
            'subsystems' => ['camera' => ['status' => 'fail', 'detail' => 'sin permiso']],
            'device' => ['battery_pct' => 50],
            'uptime_s' => 1200,
            'app_version' => '1.6',
            'app_build' => 16,
        ], ['X-Device-Key' => 'k-h'])->assertOk()->assertJsonPath('overall', 'warn');

        $h = DeviceHealth::where('device_id', $d->id)->first();
        $this->assertSame('warn', $h->overall);
        $this->assertSame('fail', $h->subsystems['camera']['status']);
        $this->assertNotNull($d->fresh()->last_seen_at);
    }

    public function test_health_reported_at_es_hora_del_servidor_no_del_equipo(): void
    {
        // Obs 168: aunque el equipo mande un ts desfasado (reloj atrasado), reported_at debe ser ~now().
        $d = $this->device();

        $this->postJson('/api/v1/health', [
            'overall' => 'ok',
            'ts' => now()->subDays(3)->toIso8601String(), // reloj del equipo MUY atrasado
        ], ['X-Device-Key' => 'k-h'])->assertOk();

        $h = DeviceHealth::where('device_id', $d->id)->first();
        // reported_at del servidor: a lo sumo 1 min de antiguedad, no 3 dias.
        $this->assertLessThan(60, abs($h->reported_at->diffInSeconds(now())));
    }

    public function test_health_persiste_last_restart_dentro_de_device(): void
    {
        // Obs 170: el equipo manda last_restart dentro de device -> debe quedar en device_metrics.
        $d = $this->device();

        $this->postJson('/api/v1/health', [
            'overall' => 'ok',
            'device' => [
                'battery_pct' => 60,
                'last_restart' => ['ts' => '2026-06-21T20:00:00-03:00', 'level' => 'app', 'reason' => 'remote', 'ok' => true],
            ],
        ], ['X-Device-Key' => 'k-h'])->assertOk();

        $h = DeviceHealth::where('device_id', $d->id)->first();
        $this->assertSame('app', $h->device_metrics['last_restart']['level']);
        $this->assertSame('remote', $h->device_metrics['last_restart']['reason']);
    }

    public function test_health_valida_overall(): void
    {
        $this->device();
        $this->postJson('/api/v1/health', ['overall' => 'banana'], ['X-Device-Key' => 'k-h'])
            ->assertStatus(422);
    }

    public function test_healthz_global_ok_y_degradado(): void
    {
        config(['health.offline_minutes' => 5]);
        $d = $this->device();

        // Sin salud aun -> sin heartbeat -> offline -> 503.
        $this->getJson('/api/v1/healthz')->assertStatus(503);

        DeviceHealth::create([
            'device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now(),
        ]);
        $this->getJson('/api/v1/healthz')->assertOk()->assertJsonPath('ok', true);

        // Latido viejo -> offline -> 503.
        DeviceHealth::where('device_id', $d->id)->update(['reported_at' => now()->subMinutes(30)]);
        $this->getJson('/api/v1/healthz')->assertStatus(503);
    }

    public function test_healthz_por_equipo(): void
    {
        config(['health.offline_minutes' => 5]);
        $d = $this->device();
        DeviceHealth::create(['device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now()]);

        $this->getJson('/api/v1/healthz?device=tel-01')->assertOk()->assertJsonPath('status', 'ok');
        $this->getJson('/api/v1/healthz?device=desconocido')->assertStatus(404);
    }

    public function test_alertas_avisan_caida_y_recuperacion_con_antispam(): void
    {
        Notification::fake();
        config(['health.offline_minutes' => 5, 'health.alert_email' => 'ops@example.com']);
        $d = $this->device();

        // Equipo caido (latido viejo).
        DeviceHealth::create(['device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now()->subMinutes(20), 'alerted' => false]);

        $this->artisan('health:check-alerts')->assertExitCode(0);
        Notification::assertSentOnDemandTimes(DeviceHealthAlert::class, 1);
        $this->assertTrue(DeviceHealth::where('device_id', $d->id)->first()->alerted);

        // Segunda corrida sin cambio de estado: no re-alerta (anti-spam).
        $this->artisan('health:check-alerts')->assertExitCode(0);
        Notification::assertSentOnDemandTimes(DeviceHealthAlert::class, 1);

        // Recuperacion: latido fresco -> avisa recuperacion y limpia alerted.
        DeviceHealth::where('device_id', $d->id)->update(['reported_at' => now(), 'overall' => 'ok']);
        $this->artisan('health:check-alerts')->assertExitCode(0);
        Notification::assertSentOnDemandTimes(DeviceHealthAlert::class, 2);
        $this->assertFalse(DeviceHealth::where('device_id', $d->id)->first()->alerted);
    }

    public function test_pagina_salud_carga(): void
    {
        $user = User::factory()->create();
        $d = $this->device();
        DeviceHealth::create(['device_id' => $d->id, 'overall' => 'ok', 'reported_at' => now()]);

        $this->actingAs($user)->get('/admin/salud')->assertOk();
        Livewire::actingAs($user)->test(ListDeviceHealth::class)->assertOk();
    }
}
