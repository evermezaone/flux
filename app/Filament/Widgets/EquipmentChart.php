<?php

namespace App\Filament\Widgets;

use App\Models\Site;
use App\Models\Telemetry;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** Serie temporal de salud del equipo por hora, filtrable por sitio (REQ-0011). */
class EquipmentChart extends ChartWidget
{
    protected static ?int $sort = 2; // FLX-0055: debajo del resumen numerico.

    protected ?string $heading = 'Salud del equipo por hora (7 dias)';

    public ?string $filter = null;

    protected function getFilters(): ?array
    {
        return Site::orderBy('code')->pluck('code', 'id')->all();
    }

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
                AVG(battery_pct) as bat,
                AVG(temp_c) as temp,
                AVG(cpu_pct) as cpu,
                AVG(mem_pct) as mem,
                AVG(storage_free_pct) as alm")
            ->groupByRaw($h)
            ->orderByRaw($h)
            ->get();

        return [
            'datasets' => [
                ['label' => 'Bateria %', 'data' => $rows->map(fn ($r) => round((float) $r->bat, 1))->all()],
                ['label' => 'Temp C', 'data' => $rows->map(fn ($r) => round((float) $r->temp, 1))->all()],
                ['label' => 'CPU %', 'data' => $rows->map(fn ($r) => round((float) $r->cpu, 1))->all()],
                ['label' => 'Mem %', 'data' => $rows->map(fn ($r) => round((float) $r->mem, 1))->all()],
                ['label' => 'Almacenamiento libre %', 'data' => $rows->map(fn ($r) => round((float) $r->alm, 1))->all()],
            ],
            'labels' => $rows->pluck('hora')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
