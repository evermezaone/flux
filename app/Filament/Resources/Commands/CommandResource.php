<?php

namespace App\Filament\Resources\Commands;

use App\Filament\Resources\Commands\Pages\ListCommands;
use App\Filament\Resources\Commands\Tables\CommandsTable;
use App\Models\Command;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Trazabilidad de comandos (FLX REQ-0015): consultar que se envio a cada dispositivo y que
 * respondio (done/failed + detalle), con el ciclo de vida completo. Solo lectura.
 */
class CommandResource extends Resource
{
    protected static ?string $model = Command::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'comando';

    protected static ?string $pluralModelLabel = 'comandos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]); // solo lectura
    }

    public static function table(Table $table): Table
    {
        return CommandsTable::configure($table);
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
            'index' => ListCommands::route('/'),
        ];
    }
}
