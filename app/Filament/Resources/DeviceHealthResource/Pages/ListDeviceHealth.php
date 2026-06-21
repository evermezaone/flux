<?php

namespace App\Filament\Resources\DeviceHealthResource\Pages;

use App\Filament\Resources\DeviceHealthResource\DeviceHealthResource;
use Filament\Resources\Pages\ListRecords;

class ListDeviceHealth extends ListRecords
{
    protected static string $resource = DeviceHealthResource::class;

    protected function getHeaderActions(): array
    {
        return []; // solo lectura
    }
}
