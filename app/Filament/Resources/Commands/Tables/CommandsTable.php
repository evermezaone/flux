<?php

namespace App\Filament\Resources\Commands\Tables;

use App\Models\Command;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Tabla de trazabilidad de comandos (FLX REQ-0015): que se envio y que respondio el dispositivo.
 */
class CommandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['device', 'events']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('device.code')->label('Dispositivo')->searchable(),
                TextColumn::make('cmd')->label('Comando')->badge()->searchable(),
                TextColumn::make('params')
                    ->label('Params')
                    ->formatStateUsing(fn ($state): string => filled($state) ? json_encode($state) : '-'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success',
                        'failed' => 'danger',
                        'sent' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('result')->label('Respuesta')->wrap()->placeholder('-'),
                TextColumn::make('created_at')->label('Creado')->dateTime()->sortable(),
                TextColumn::make('picked_at')->label('Entregado')->dateTime()->placeholder('-'),
                TextColumn::make('done_at')->label('Respondido')->dateTime()->placeholder('-'),
                TextColumn::make('trace')
                    ->label('Trazas')
                    ->state(fn (Command $record): string => self::trace($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    'pending' => 'pending',
                    'sent' => 'sent',
                    'done' => 'done',
                    'failed' => 'failed',
                ]),
            ]);
    }

    /** Resume el ciclo de vida del comando a partir de sus eventos. */
    private static function trace(Command $record): string
    {
        if ($record->events->isEmpty()) {
            return '-';
        }

        return $record->events
            ->map(function ($e): string {
                $when = $e->created_at?->format('d/m H:i:s') ?? '';
                $note = filled($e->note) ? " ({$e->note})" : '';

                return "{$e->event} {$when}{$note}";
            })
            ->implode('  ·  ');
    }
}
