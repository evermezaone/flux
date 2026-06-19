<?php

namespace App\Filament\Resources\Devices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('device_key')
                    ->label('Device Key (token)')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('model')
                    ->default(null),
                DateTimePicker::make('last_seen_at'),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
