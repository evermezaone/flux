<?php

namespace App\Filament\Resources\Telemetry;

use App\Filament\Resources\Telemetry\Pages\ListTelemetry;
use App\Filament\Resources\Telemetry\Tables\TelemetryTable;
use App\Models\Telemetry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Listado de telemetria (FLX REQ-0023): tabla solo lectura de los registros enviados por los
 * equipos (trafico + salud del equipo).
 */
class TelemetryResource extends Resource
{
    protected static ?string $model = Telemetry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $modelLabel = 'telemetría';

    protected static ?string $pluralModelLabel = 'telemetría';

    protected static ?string $slug = 'telemetria';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]); // solo lectura
    }

    public static function table(Table $table): Table
    {
        return TelemetryTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemetry::route('/'),
        ];
    }
}
