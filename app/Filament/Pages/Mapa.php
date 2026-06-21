<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Mapa de cruces y dispositivos con sus metricas de telemetria (FLX REQ-0025).
 * Leaflet + OpenStreetMap (sin API key). Marcadores coloreados por congestion y dimensionados por
 * intensidad, popup con ultimas metricas, capa heatmap, y modo en vivo / historico.
 * Los datos los provee GET /api/v1/map (auth de sesion).
 */
class Mapa extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $title = 'Mapa';

    protected static ?string $navigationLabel = 'Mapa';

    protected string $view = 'filament.pages.mapa';

    /** URL del endpoint de datos (respeta el subdirectorio /flux via route()). */
    protected function getViewData(): array
    {
        return [
            'mapDataUrl' => route('api.map'),
        ];
    }
}
