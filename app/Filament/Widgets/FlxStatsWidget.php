<?php

namespace App\Filament\Widgets;

use App\Models\Command;
use App\Models\Device;
use App\Models\Site;
use App\Models\Telemetry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Resumen operativo en el dashboard del panel (REQ-0006). Graficos de series = REQ-0011. */
class FlxStatsWidget extends StatsOverviewWidget
{
    // FLX-0055: el resumen numerico va PRIMERO (arriba) y a todo el ancho; los graficos quedan debajo.
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Cruces', Site::count()),
            Stat::make('Dispositivos', Device::count())
                ->description(Device::where('active', true)->count().' activos'),
            Stat::make('Telemetria (registros)', Telemetry::count()),
            Stat::make('Comandos pendientes', Command::where('status', 'pending')->count()),
        ];
    }
}
