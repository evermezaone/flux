<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryIngestTest extends TestCase
{
    use RefreshDatabase;

    private function device(bool $active = true): Device
    {
        $site = Site::create(['code' => 'ruta2_cruce1', 'name' => 'Cruce 1']);

        return Device::create([
            'site_id' => $site->id,
            'code' => 'tel-01',
            'device_key' => 'k-123',
            'active' => $active,
        ]);
    }

    private function record(int $seq): array
    {
        return [
            'ts' => '2026-06-19T18:30:00-03:00',
            'client_seq' => $seq,
            'site_id' => 'ruta2_cruce1',
            'traffic' => [
                'zone' => 'CROSS', 'occupancy' => 7, 'queue_len_m' => 42.0,
                'pressure' => 7, 'congestion' => 'high', 'decision' => 'B_green',
                'wait_est_s' => 23.5, 'empty_s' => 0,
            ],
            'device' => [
                'battery_pct' => 78, 'temp_c' => 41.2, 'cpu_pct' => 55,
                'mem_pct' => 60, 'storage_free_pct' => 34,
            ],
        ];
    }

    public function test_sin_device_key_devuelve_401(): void
    {
        $this->postJson('/api/v1/telemetry', $this->record(1))->assertStatus(401);
    }

    public function test_ingesta_single_persiste_y_convierte_a_utc(): void
    {
        $d = $this->device();

        $this->postJson('/api/v1/telemetry', $this->record(1), ['X-Device-Key' => 'k-123'])
            ->assertOk()
            ->assertJson(['ok' => true, 'accepted' => 1, 'duplicated' => 0]);

        // 18:30 -03:00 -> 21:30 UTC
        $this->assertDatabaseHas('telemetry', [
            'device_id' => $d->id,
            'client_seq' => 1,
            'congestion' => 'high',
            'decision' => 'B_green',
            'ts' => '2026-06-19 21:30:00',
        ]);
        $this->assertNotNull($d->fresh()->last_seen_at);
    }

    public function test_batch_e_idempotencia(): void
    {
        $this->device();
        $batch = ['records' => [$this->record(10), $this->record(11)]];

        $this->postJson('/api/v1/telemetry', $batch, ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['accepted' => 2, 'duplicated' => 0]);

        // Reenvio del mismo lote: deduplicado por (device_id, client_seq).
        $this->postJson('/api/v1/telemetry', $batch, ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['accepted' => 0, 'duplicated' => 2]);

        $this->assertSame(2, \App\Models\Telemetry::count());
    }

    public function test_registro_invalido_no_frena_el_lote(): void
    {
        $this->device();
        $bad = $this->record(20);
        unset($bad['ts']); // invalido
        $batch = ['records' => [$bad, $this->record(21)]];

        $this->postJson('/api/v1/telemetry', $batch, ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['accepted' => 1, 'invalid' => 1]);
    }

    public function test_mismo_client_seq_distinto_ts_no_se_descarta(): void
    {
        // FLX-0045: el bug que se arregla. Al reinstalar la app, client_seq reinicia y reusa numeros; con
        // dedup por (device_id, client_seq) la segunda lectura se perdia. Ahora la clave es por contenido.
        $this->device();
        $a = $this->record(5);
        $b = $this->record(5); // MISMO client_seq...
        $b['ts'] = '2026-06-19T18:31:00-03:00'; // ...pero ts (y contenido) distinto

        $this->postJson('/api/v1/telemetry', ['records' => [$a, $b]], ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['accepted' => 2, 'duplicated' => 0]);

        $this->assertSame(2, \App\Models\Telemetry::count());
    }

    public function test_reintento_identico_deduplica(): void
    {
        // Idempotencia preservada: el mismo registro reenviado (red cortada) no se duplica.
        $this->device();
        $rec = $this->record(7);

        $this->postJson('/api/v1/telemetry', $rec, ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['accepted' => 1]);
        $this->postJson('/api/v1/telemetry', $rec, ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['accepted' => 0, 'duplicated' => 1]);

        $this->assertSame(1, \App\Models\Telemetry::count());
    }

    public function test_client_hash_del_telefono_se_respeta(): void
    {
        // Si el telefono manda client_hash (VLS-0065), manda la clave: mismo hash -> dedup aunque cambie algo.
        $this->device();
        $a = $this->record(1);
        $a['client_hash'] = str_repeat('a', 64);
        $b = $this->record(2);          // distinto client_seq y ts...
        $b['ts'] = '2026-06-19T19:00:00-03:00';
        $b['client_hash'] = str_repeat('a', 64); // ...pero mismo hash declarado

        $this->postJson('/api/v1/telemetry', ['records' => [$a, $b]], ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['accepted' => 1, 'duplicated' => 1]);

        $this->assertSame(1, \App\Models\Telemetry::count());
    }

    public function test_media_upsert_por_file(): void
    {
        $this->device();
        $payload = [
            'tipo' => 'clip', 'file' => '20260619_1830.mp4',
            'ts_start' => '2026-06-19T18:30:00-03:00', 'size_mb' => 95, 'available' => true,
        ];

        $this->postJson('/api/v1/media', $payload, ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['ok' => true, 'created' => true]);

        // Mismo file -> upsert (no crea otro).
        $this->postJson('/api/v1/media', array_merge($payload, ['size_mb' => 120]), ['X-Device-Key' => 'k-123'])
            ->assertOk()->assertJson(['created' => false]);

        $this->assertSame(1, \App\Models\Media::count());
    }

    public function test_health_pct_fuera_de_rango_es_invalido(): void
    {
        $this->device();
        $bad = $this->record(30);
        $bad['device']['battery_pct'] = 180; // > 100: imposible
        $batch = ['records' => [$bad, $this->record(31)]];

        $this->postJson('/api/v1/telemetry', $batch, ['X-Device-Key' => 'k-123'])
            ->assertOk()
            ->assertJson(['accepted' => 1, 'invalid' => 1]); // el valido entra; el de 180% no

        // el registro invalido no se persistio
        $this->assertDatabaseMissing('telemetry', ['battery_pct' => 180]);
    }
}
