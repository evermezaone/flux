<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use App\Models\Telemetry;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** Serie temporal de trafico por hora, filtrable por sitio (REQ-0011). */
class TrafficChart extends ChartWidget
{
    protected static ?int $sort = 3; // FLX-0055: debajo del resumen numerico.

    protected ?string $heading = 'Trafico por hora (7 dias)';

    public ?string $filter = null;

    protected function getFilters(): ?array
    {
        return Site::orderBy('code')->pluck('code', 'id')->all();
    }

    /** Bucket horario compatible con MariaDB y sqlite. */
    private function hourExpr(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:00:00', ts)"
            : "DATE_FORMAT(ts, '%Y-%m-%d %H:00:00')";
    }

    protected function getData(): array
    {
        $siteId = $this->filter ?: Site::min('id');
        if (! $siteId) {
            return ['datasets' => [], 'labels' => []];
        }

        $h = $this->hourExpr();
        $rows = Telemetry::query()
            ->where('site_id', $siteId)
            ->where('ts', '>=', now()->subDays(7))
            ->selectRaw("$h as hora,
                AVG(occupancy) as occ,
                MAX(pressure) as pres,
                AVG(queue_len_m) as cola")
            ->groupByRaw($h)
            ->orderByRaw($h)
            ->get();

        return [
            'datasets' => [
                ['label' => 'Ocupacion (prom)', 'data' => $rows->map(fn ($r) => round((float) $r->occ, 2))->all()],
                ['label' => 'Presion (max)', 'data' => $rows->pluck('pres')->all()],
                ['label' => 'Cola m (prom)', 'data' => $rows->map(fn ($r) => round((float) $r->cola, 1))->all()],
            ],
            'labels' => $rows->pluck('hora')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
