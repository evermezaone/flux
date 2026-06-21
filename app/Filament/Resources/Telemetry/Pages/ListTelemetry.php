<?php

namespace App\Filament\Resources\Telemetry\Pages;

use App\Filament\Resources\Telemetry\TelemetryResource;
use Filament\Resources\Pages\ListRecords;

class ListTelemetry extends ListRecords
{
    protected static string $resource = TelemetryResource::class;

    protected function getHeaderActions(): array
    {
        return []; // solo lectura
    }
}
