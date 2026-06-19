<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Telemetry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Consulta de telemetria para el panel: series (raw|hour) y KPIs. */
class TelemetryQueryController extends Controller
{
    /** Expresion de bucket por hora compatible con MariaDB y sqlite (tests). */
    private function hourExpr(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:00:00', ts)"
            : "DATE_FORMAT(ts, '%Y-%m-%d %H:00:00')";
    }

    private function baseQuery(Request $request): array
    {
        $data = $request->validate([
            'site' => ['required', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'agg' => ['nullable', 'in:raw,hour'],
        ]);

        $site = Site::where('code', $data['site'])->first();
        abort_if(! $site, 404, 'Cruce no encontrado');

        $q = Telemetry::query()->where('site_id', $site->id);
        if (! empty($data['from'])) {
            $q->where('ts', '>=', Carbon::parse($data['from'])->utc());
        }
        if (! empty($data['to'])) {
            $q->where('ts', '<=', Carbon::parse($data['to'])->utc());
        }

        return [$q, $data, $site];
    }

    /** GET /telemetry?site=&from=&to=&agg=raw|hour */
    public function index(Request $request): JsonResponse
    {
        /** @var Builder $q */
        [$q, $data] = $this->baseQuery($request);
        $agg = $data['agg'] ?? 'raw';

        if ($agg === 'hour') {
            $h = $this->hourExpr();
            $rows = $q->selectRaw("$h as hora,
                    COUNT(*) as n,
                    AVG(occupancy) as occupancy_avg,
                    MAX(pressure) as pressure_max,
                    AVG(queue_len_m) as queue_len_avg,
                    AVG(wait_est_s) as wait_est_avg,
                    SUM(empty_s) as empty_s_sum")
                ->groupByRaw($h)
                ->orderByRaw($h)
                ->get();
        } else {
            $rows = $q->select(
                'ts', 'zone', 'occupancy', 'queue_len_m', 'pressure',
                'congestion', 'decision', 'wait_est_s', 'empty_s'
            )->orderBy('ts')->limit(5000)->get();
        }

        return response()->json(['ok' => true, 'agg' => $agg, 'rows' => $rows]);
    }

    /** GET /summary?site=&from=&to= — KPIs precalculados. */
    public function summary(Request $request): JsonResponse
    {
        /** @var Builder $q */
        [$q] = $this->baseQuery($request);
        $h = $this->hourExpr();

        $total = (clone $q)->count();
        $occProm = (clone $q)->avg('occupancy');
        $waitProm = (clone $q)->avg('wait_est_s');
        $saturadas = (clone $q)->where('congestion', 'saturated')->count();

        $peak = (clone $q)->selectRaw("$h as hora, SUM(occupancy) as occ")
            ->groupByRaw($h)->orderByDesc('occ')->first();

        return response()->json([
            'ok' => true,
            // 'total' = muestras en el rango. occupancy es instantanea (no throughput),
            // por eso el "total de vehiculos" del contrato se aproxima con ocupacion_promedio.
            'muestras' => $total,
            'ocupacion_promedio' => $occProm !== null ? round((float) $occProm, 2) : null,
            'espera_promedio_s' => $waitProm !== null ? round((float) $waitProm, 2) : null,
            'saturacion_pct' => $total ? round($saturadas * 100 / $total, 1) : 0.0,
            'hora_pico' => $peak?->hora,
        ]);
    }
}
