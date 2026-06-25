<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Filament\Resources\Devices\DeviceResource;
use App\Models\Device;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.code')
                    ->label('Cruce')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Dispositivo')
                    ->searchable(),
                TextColumn::make('model')
                    ->toggleable(isToggledHiddenByDefault: true),
                // device_key NO se muestra (es un secreto).
                TextColumn::make('last_seen_at')
                    ->label('Visto')
                    ->since()
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
                // FLX-0057: salud resumida (badge) en el listado compacto.
                TextColumn::make('health.overall')
                    ->label('Salud')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?string $state): string => match ($state) {
                        'ok' => 'success',
                        'warn' => 'warning',
                        'fail' => 'danger',
                        default => 'gray',
                    }),
                // FLX-0057: version VLS/Sentinel (del bloque apps si existe).
                TextColumn::make('versiones')
                    ->label('VLS / Sentinel')
                    ->placeholder('—')
                    ->state(fn (Device $record): string => trim(
                        (data_get($record->health?->device_metrics, 'apps.vls.version_name') ?? ($record->health?->app_version ?? '—'))
                        .' / '.(data_get($record->health?->device_metrics, 'apps.sentinel.version_name') ?? '—')
                    ))
                    ->toggleable(),
                // FLX-0041: estado del supervisor remoto + por que (que accion se intento).
                TextColumn::make('supervision.state')
                    ->label('Supervisor')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'requiere_intervencion' => 'requiere intervención',
                        'sin_metricas' => 'sin métricas',
                        default => (string) ($state ?? '—'),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'online' => 'success',
                        'degradado', 'sin_metricas', 'recuperando' => 'warning',
                        'requiere_intervencion' => 'danger',
                        default => 'gray',
                    }),
                // FLX-0057: el motivo largo del supervisor sale del listado (vive en la ficha) -> sin scroll horizontal.
                TextColumn::make('supervision.reason')
                    ->label('Supervisor: motivo / última acción')
                    ->placeholder('—')
                    ->wrap()
                    ->formatStateUsing(fn (?string $state, $record): string => trim(
                        ($record->supervision?->last_action ? $record->supervision->last_action.' · ' : '').((string) ($state ?? ''))
                    ))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // FLX-0057: click en la fila -> ficha central de gestion del dispositivo.
            ->recordUrl(fn (Device $record): string => DeviceResource::getUrl('view', ['record' => $record]))
            // FLX-0057 (Codex R1): listado COMPACTO. La operacion (reiniciar/despertar/detener/reanudar/diagnostico/
            // logs/comandos) vive en la ficha central (ViewDevice, header), NO como botones por fila -> sin scroll
            // horizontal. Aca solo abrir la ficha y editar.
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
