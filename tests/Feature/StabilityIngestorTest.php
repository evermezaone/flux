<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use App\Models\StabilityEvent;
use App\Services\StabilityIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0047: ingesta de eventos de estabilidad (idempotencia + agregados + status).
 */
class StabilityIngestorTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $code = 'tel-st'): Device
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);

        return Device::create(['site_id' => $site->id, 'code' => $code, 'device_key' => 'k-'.$code, 'active' => true]);
    }

    private function block(array $events, array $extra = []): array
    {
        return array_merge(['ui_frozen' => false, 'events' => $events], $extra);
    }

    public function test_persiste_y_es_idempotente(): void
    {
        $d = $this->device();
        $ev = ['event_id' => 'e1', 'event_type' => 'anr_suspected', 'severity' => 'warn', 'occurred_at' => now()->toIso8601String(), 'summary' => 'ANR'];

        $svc = app(StabilityIngestor::class);
        $svc->ingest($d, $this->block([$ev]));
        $svc->ingest($d, $this->block([$ev])); // reenvio -> no duplica

        $this->assertSame(1, StabilityEvent::where('device_id', $d->id)->count());
    }

    public function test_agregados_y_status(): void
    {
        $d = $this->device();
        $now = now()->toIso8601String();
        $st = app(StabilityIngestor::class)->ingest($d, $this->block([
            ['event_id' => 'c1', 'event_type' => 'crash', 'severity' => 'critical', 'occurred_at' => $now],
            ['event_id' => 'a1', 'event_type' => 'anr_suspected', 'severity' => 'warn', 'occurred_at' => $now],
            ['event_id' => 'u1', 'event_type' => 'ui_frozen_timeout', 'severity' => 'warn', 'occurred_at' => $now],
        ]));

        $this->assertSame(1, $st->crash_count_24h);
        $this->assertSame(1, $st->anr_count_24h);
        $this->assertSame(1, $st->ui_freeze_count_24h);
        $this->assertSame('critical', $st->stability_status); // crash -> critical
    }

    public function test_ui_frozen_es_critical(): void
    {
        $d = $this->device();
        $st = app(StabilityIngestor::class)->ingest($d, $this->block([], ['ui_frozen' => true, 'ui_last_tick_at' => now()->subMinute()->toIso8601String()]));
        $this->assertTrue($st->ui_frozen);
        $this->assertSame('critical', $st->stability_status);
    }

    public function test_solo_warn_sin_crash(): void
    {
        $d = $this->device();
        $st = app(StabilityIngestor::class)->ingest($d, $this->block([
            ['event_id' => 'a1', 'event_type' => 'anr_suspected', 'severity' => 'warn', 'occurred_at' => now()->toIso8601String()],
        ]));
        $this->assertSame('warn', $st->stability_status);
    }

    public function test_ingesta_via_heartbeat(): void
    {
        $d = $this->device('tel-hb');
        $payload = [
            'overall' => 'ok',
            'device' => ['stability' => $this->block([
                ['event_id' => 'x1', 'event_type' => 'crash', 'severity' => 'critical', 'occurred_at' => now()->toIso8601String()],
            ])],
        ];

        $this->postJson('/api/v1/health', $payload, ['X-Device-Key' => 'k-tel-hb'])->assertOk();

        $this->assertDatabaseHas('stability_events', ['device_id' => $d->id, 'event_id' => 'x1', 'event_type' => 'crash']);
        $this->assertSame('critical', $d->stabilityState()->first()->stability_status);
    }

    public function test_sin_bloque_no_rompe(): void
    {
        $d = $this->device('tel-none');
        DeviceHealth::updateOrCreate(['device_id' => $d->id], ['overall' => 'ok', 'reported_at' => now()]);
        $this->assertNull(app(StabilityIngestor::class)->ingest($d, null));
    }

    public function test_application_error_no_queda_ok(): void
    {
        // FLX-0047 R2: canonico app_error_suspected (VLS-0068) cuenta y deja en warn (no ok).
        $d = $this->device('tel-ae');
        $st = app(StabilityIngestor::class)->ingest($d, $this->block([
            ['event_id' => 'ae1', 'event_type' => 'app_error_suspected', 'severity' => 'warn', 'occurred_at' => now()->toIso8601String()],
        ]));
        $this->assertSame(1, $st->app_error_count_24h);
        $this->assertSame('warn', $st->stability_status);
    }

    public function test_application_error_alias_tambien_cuenta(): void
    {
        // FLX-0047 R2: el alias application_error_suspected (VLS-0071) tambien suma a app_error_count_24h.
        $d = $this->device('tel-ae2');
        $st = app(StabilityIngestor::class)->ingest($d, $this->block([
            ['event_id' => 'ae2', 'event_type' => 'application_error_suspected', 'severity' => 'warn', 'occurred_at' => now()->toIso8601String()],
        ]));
        $this->assertSame(1, $st->app_error_count_24h);
    }

    public function test_recurrencia_eleva_a_critical(): void
    {
        // FLX-0047 R1: 3 eventos warn en 24h (umbral recurrent_events) -> critical aunque ninguno sea crash.
        config(['stability.recurrent_events' => 3]);
        $d = $this->device('tel-rec');
        $events = [];
        foreach (range(1, 3) as $i) {
            $events[] = ['event_id' => "ae$i", 'event_type' => 'application_error_suspected', 'severity' => 'warn', 'occurred_at' => now()->toIso8601String()];
        }
        $st = app(StabilityIngestor::class)->ingest($d, $this->block($events));
        $this->assertSame('critical', $st->stability_status);
    }
}
