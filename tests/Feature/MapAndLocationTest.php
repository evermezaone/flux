<?php

namespace Tests\Feature;

use App\Filament\Pages\Mapa;
use App\Models\Device;
use App\Models\Site;
use App\Models\Telemetry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mapa de cruces + recepcion de ubicacion GPS (FLX REQ-0025).
 */
class MapAndLocationTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $key = 'k-loc', string $code = 'tel-01'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create([
            'site_id' => $site->id, 'code' => $code, 'device_key' => $key, 'active' => true,
        ]);
    }

    public function test_location_requiere_device_key(): void
    {
        $this->postJson('/api/v1/location', ['lat' => -25.3, 'lng' => -57.6])->assertStatus(401);
    }

    public function test_location_actualiza_la_ubicacion_del_cruce(): void
    {
        $d = $this->device();

        $this->postJson('/api/v1/location', [
            'lat' => -25.3001, 'lng' => -57.6002, 'accuracy_m' => 12.5,
        ], ['X-Device-Key' => 'k-loc'])
            ->assertOk()
            ->assertJsonPath('applied', true);

        $site = Site::firstWhere('code', 'ruta2_cruce1');
        $this->assertEquals(-25.3001, (float) $site->lat);
        $this->assertEquals(-57.6002, (float) $site->lng);
        $this->assertEquals(12.5, (float) $site->location_accuracy_m);
        $this->assertNotNull($site->location_updated_at);
    }

    public function test_location_no_sobreescribe_si_es_manual(): void
    {
        $d = $this->device();
        $site = Site::firstWhere('code', 'ruta2_cruce1');
        $site->forceFill(['lat' => -25.1, 'lng' => -57.1, 'location_manual' => true])->save();

        $this->postJson('/api/v1/location', [
            'lat' => -10.0, 'lng' => -10.0,
        ], ['X-Device-Key' => 'k-loc'])
            ->assertOk()
            ->assertJsonPath('applied', false);

        $site->refresh();
        $this->assertEquals(-25.1, (float) $site->lat); // sin cambios (manual manda)
        $this->assertEquals(-57.1, (float) $site->lng);
    }

    public function test_location_valida_rango(): void
    {
        $this->device();
        $this->postJson('/api/v1/location', ['lat' => 999, 'lng' => -57.6], ['X-Device-Key' => 'k-loc'])
            ->assertStatus(422);
    }

    public function test_map_vivo_devuelve_cruces_con_ultima_telemetria(): void
    {
        $user = User::factory()->create();
        $d = $this->device();
        $site = Site::firstWhere('code', 'ruta2_cruce1');
        $site->forceFill(['lat' => -25.3, 'lng' => -57.6])->save();

        Telemetry::create([
            'device_id' => $d->id, 'site_id' => $site->id, 'ts' => now()->subMinutes(5),
            'client_seq' => 1, 'zone' => 'CROSS', 'occupancy' => 3, 'pressure' => 3,
            'congestion' => 'low', 'decision' => 'A_green', 'battery_pct' => 80,
        ]);
        Telemetry::create([
            'device_id' => $d->id, 'site_id' => $site->id, 'ts' => now(),
            'client_seq' => 2, 'zone' => 'CROSS', 'occupancy' => 9, 'pressure' => 9,
            'congestion' => 'high', 'decision' => 'B_green', 'battery_pct' => 78,
        ]);

        $res = $this->actingAs($user)->getJson('/api/v1/map')->assertOk();
        $res->assertJsonPath('mode', 'vivo');
        $res->assertJsonPath('sites.0.code', 'ruta2_cruce1');
        $res->assertJsonPath('sites.0.last.congestion', 'high'); // el mas reciente
    }

    public function test_map_historico_usa_el_valor_a_esa_fecha(): void
    {
        $user = User::factory()->create();
        $d = $this->device();
        $site = Site::firstWhere('code', 'ruta2_cruce1');
        $site->forceFill(['lat' => -25.3, 'lng' => -57.6])->save();

        Telemetry::create([
            'device_id' => $d->id, 'site_id' => $site->id, 'ts' => now()->subHours(2),
            'client_seq' => 1, 'congestion' => 'low', 'pressure' => 2, 'battery_pct' => 90,
        ]);
        Telemetry::create([
            'device_id' => $d->id, 'site_id' => $site->id, 'ts' => now(),
            'client_seq' => 2, 'congestion' => 'high', 'pressure' => 9, 'battery_pct' => 70,
        ]);

        $at = now()->subHour()->toIso8601String();
        $res = $this->actingAs($user)->getJson('/api/v1/map?at=' . urlencode($at))->assertOk();
        $res->assertJsonPath('mode', 'historico');
        $res->assertJsonPath('sites.0.last.congestion', 'low'); // el de hace 2h, no el actual
    }

    public function test_map_separa_cruces_sin_ubicacion(): void
    {
        $user = User::factory()->create();
        $this->device();
        Site::create(['code' => 'sin_geo']); // sin lat/lng

        $res = $this->actingAs($user)->getJson('/api/v1/map')->assertOk();
        $codes = collect($res->json('unlocated'))->pluck('code');
        $this->assertTrue($codes->contains('sin_geo'));
    }

    public function test_pagina_mapa_carga(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/mapa')->assertOk();
        Livewire::actingAs($user)->test(Mapa::class)->assertOk();
    }

    public function test_popup_del_mapa_escapa_html_antixss(): void
    {
        // El popup se arma en JS via innerHTML con datos externos (los manda el equipo).
        // Guard de regresion: la pagina debe entregar el escape de entidades real.
        $user = User::factory()->create();
        $res = $this->actingAs($user)->get('/admin/mapa')->assertOk();

        // El helper esc() debe reemplazar < y & por entidades (anti-XSS).
        $res->assertSee("replace(/</g, '&lt;')", false);
        $res->assertSee("replace(/&/g, '&amp;')", false);
    }
}
