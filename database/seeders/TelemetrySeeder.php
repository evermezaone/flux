<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Site;
use App\Models\Telemetry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Telemetria sintetica para probar el panel sin la app (REQ-0007).
 * Genera ~1 semana de datos cada 10 min, con el patron de migracion de fin de semana
 * (pico saliente viernes/sabado por la tarde, entrante domingo). Idempotente: insertOrIgnore
 * por (device_id, client_hash) (FLX-0045), asi re-correr no duplica.
 */
class TelemetrySeeder extends Seeder
{
    /** Inicio fijo (lunes) para datos reproducibles. */
    public const START = '2026-06-08 00:00:00';

    public const DAYS = 7;

    public const STEP_MIN = 10;

    public function run(): void
    {
        mt_srand(20260608); // ruido reproducible

        $site = Site::firstOrCreate(
            ['code' => 'ruta2_cruce1'],
            ['name' => 'Ruta 2 - Cruce 1', 'lat' => -25.300000, 'lng' => -57.600000]
        );

        $device = Device::firstOrCreate(
            ['code' => 'tel-ruta2-01'],
            ['site_id' => $site->id, 'device_key' => 'seed-ruta2-01-key', 'model' => 'Seed Phone', 'active' => true]
        );

        $start = Carbon::parse(self::START, 'UTC');
        $end = $start->copy()->addDays(self::DAYS);
        $cursor = $start->copy();
        $seq = 0;
        $rows = [];

        while ($cursor < $end) {
            $seq++;
            $occ = $this->occupancy($cursor);
            $congestion = $occ < 3 ? 'low' : ($occ < 6 ? 'med' : ($occ < 10 ? 'high' : 'saturated'));
            $decision = ($cursor->hour % 2 === 0) ? 'A_green' : 'B_green';

            $rows[] = [
                'device_id' => $device->id,
                'site_id' => $site->id,
                'ts' => $cursor->toDateTimeString(),
                'client_seq' => $seq,
                // FLX-0045: idempotencia por (device_id, client_hash). Hash estable por (device, seq) para
                // que re-correr el seeder no duplique aunque las metricas aleatorias cambien.
                'client_hash' => hash('sha256', $device->id.':'.$seq),
                'zone' => 'CROSS',
                'occupancy' => $occ,
                'queue_len_m' => round($occ * 6.5, 2),
                'pressure' => $occ,
                'congestion' => $congestion,
                'decision' => $decision,
                'wait_est_s' => round($occ * 3.2, 2),
                'empty_s' => max(0, 8 - $occ),
                'battery_pct' => $this->battery($cursor),
                'temp_c' => round(34 + ($cursor->hour >= 12 && $cursor->hour <= 17 ? 8 : 2) + mt_rand(-150, 150) / 100, 2),
                'cpu_pct' => 30 + mt_rand(0, 45),
                'mem_pct' => 45 + mt_rand(0, 35),
                'storage_free_pct' => max(8, 90 - intdiv($seq, 60)),
            ];

            if (count($rows) >= 500) {
                Telemetry::insertOrIgnore($rows);
                $rows = [];
            }
            $cursor->addMinutes(self::STEP_MIN);
        }

        if ($rows) {
            Telemetry::insertOrIgnore($rows);
        }

        $device->forceFill(['last_seen_at' => now()])->save();
    }

    /** Ocupacion por hora con patron de fin de semana. */
    private function occupancy(Carbon $t): int
    {
        $h = $t->hour;
        // Base por franja horaria.
        $base = match (true) {
            $h <= 5 => 1,
            $h >= 7 && $h <= 9 => 7,    // pico maniana
            $h >= 17 && $h <= 19 => 7,  // pico tarde
            $h >= 10 && $h <= 16 => 4,
            default => 2,
        };

        // Migracion de fin de semana:
        $dow = $t->dayOfWeek; // 0=Dom, 5=Vie, 6=Sab
        if (in_array($dow, [5, 6], true) && $h >= 16 && $h <= 20) {
            $base += 6; // saliente viernes/sabado tarde
        }
        if ($dow === 0 && $h >= 16 && $h <= 21) {
            $base += 5; // entrante domingo
        }

        $occ = $base + mt_rand(-1, 1);

        return max(0, min(15, $occ));
    }

    private function battery(Carbon $t): int
    {
        // Carga de noche, descarga de dia (3..100).
        $h = $t->hour;
        $pct = 100 - (int) round(($h / 24) * 70);

        return max(3, min(100, $pct + mt_rand(-3, 3)));
    }
}
