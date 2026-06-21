<?php

namespace App\Filament\Resources\DeviceHealthResource;

use App\Filament\Resources\DeviceHealthResource\Pages\ListDeviceHealth;
use App\Filament\Resources\DeviceHealthResource\Tables\DeviceHealthTable;
use App\Models\DeviceHealth;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Semaforo de salud de equipos (FLX REQ-0026): estado verde/amarillo/rojo/offline por equipo,
 * detalle por subsistema y antiguedad del ultimo latido. Solo lectura.
 */
class DeviceHealthResource extends Resource
{
    protected static ?string $model = DeviceHealth::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $modelLabel = 'salud de equipos';

    protected static ?string $pluralModelLabel = 'salud de equipos';

    protected static ?string $navigationLabel = 'Salud';

    protected static ?string $slug = 'salud';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]); // solo lectura
    }

    public static function table(Table $table): Table
    {
        return DeviceHealthTable::configure($table);
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
            'index' => ListDeviceHealth::route('/'),
        ];
    }
}
