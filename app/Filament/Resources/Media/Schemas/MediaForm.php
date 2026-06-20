<?php

namespace App\Filament\Resources\Media\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('device_id')
                    ->relationship('device', 'id')
                    ->required(),
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->required(),
                Select::make('tipo')
                    ->options(['timelapse' => 'Timelapse', 'clip' => 'Clip'])
                    ->required(),
                DateTimePicker::make('ts_start'),
                DateTimePicker::make('ts_end'),
                TextInput::make('file')
                    ->required(),
                TextInput::make('fps')
                    ->numeric()
                    ->default(null),
                TextInput::make('size_mb')
                    ->numeric()
                    ->default(null),
                Toggle::make('available')
                    ->required(),
                TextInput::make('url')
                    ->url()
                    ->default(null),
            ]);
    }
}
