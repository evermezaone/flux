<?php

namespace App\Filament\Resources\Devices\Schemas;

use Filament\Forms\Components\DatePicker;
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
                // FLX-0043: mantenimiento preventivo (edad operativa + reemplazo) y fuente de energia manual.
                DatePicker::make('install_date')
                    ->label('Fecha de instalación')
                    ->helperText('Para calcular edad operativa y recomendación de revisión/reemplazo.'),
                Select::make('power_source')
                    ->label('Alimentación')
                    ->options(['solar' => 'Solar', 'backup' => 'Batería de respaldo', 'grid' => 'Red eléctrica'])
                    ->helperText('Campo manual (VLS no siempre puede medir la fuente).'),
                DateTimePicker::make('last_seen_at'),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
