<?php

namespace App\Filament\Resources\Telemetry\Tables;

use App\Models\Site;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Tabla de telemetria (FLX REQ-0023): registros enviados, mas recientes primero.
 */
class TelemetryTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['device', 'site']))
            ->defaultSort('ts', 'desc')
            ->columns([
                TextColumn::make('ts')->label('Hora')->dateTime()->sortable(),
                TextColumn::make('site.code')->label('Cruce')->searchable(),
                TextColumn::make('device.code')->label('Dispositivo')->searchable(),
                TextColumn::make('zone')->label('Zona'),
                TextColumn::make('occupancy')->label('Ocup.')->numeric()->sortable(),
                TextColumn::make('pressure')->label('Presión')->numeric()->sortable(),
                TextColumn::make('congestion')
                    ->label('Congestión')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'low' => 'success',
                        'med' => 'warning',
                        'high' => 'danger',
                        'saturated' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('decision')->label('Decisión'),
                TextColumn::make('queue_len_m')->label('Cola (m)')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('wait_est_s')->label('Espera (s)')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('empty_s')->label('Vacío (s)')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('battery_pct')->label('Bat. %')->numeric()->toggleable(),
                TextColumn::make('temp_c')->label('Temp °C')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cpu_pct')->label('CPU %')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mem_pct')->label('Mem %')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('storage_free_pct')->label('Disco libre %')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client_seq')->label('seq')->numeric()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('site_id')
                    ->label('Cruce')
                    ->options(fn () => Site::orderBy('code')->pluck('code', 'id')),
                SelectFilter::make('congestion')->label('Congestión')->options([
                    'low' => 'low',
                    'med' => 'med',
                    'high' => 'high',
                    'saturated' => 'saturated',
                ]),
            ]);
    }
}
