<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->default(null),
                TextInput::make('lat')
                    ->label('Latitud')
                    ->numeric()
                    ->default(null),
                TextInput::make('lng')
                    ->label('Longitud')
                    ->numeric()
                    ->default(null),
                // REQ-0025: si esta activo, el GPS del equipo NO sobreescribe esta ubicacion.
                Toggle::make('location_manual')
                    ->label('Ubicación fijada manualmente (el GPS no la sobreescribe)')
                    ->default(false),
            ]);
    }
}
