<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Filament\Actions\DiagnosticDeviceAction;
use App\Filament\Actions\LogsDeviceActions;
use App\Filament\Actions\PushDeviceAction;
use App\Filament\Actions\RestartDeviceAction;
use App\Filament\Actions\StopAllDeviceAction;
use App\Models\Command;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
                    ->toggleable(),
                // device_key NO se muestra (es un secreto).
                TextColumn::make('last_seen_at')
                    ->label('Visto')
                    ->since()
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
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
                TextColumn::make('supervision.reason')
                    ->label('Supervisor: motivo / última acción')
                    ->placeholder('—')
                    ->wrap()
                    ->formatStateUsing(fn (?string $state, $record): string => trim(
                        ($record->supervision?->last_action ? $record->supervision->last_action.' · ' : '').((string) ($state ?? ''))
                    ))
                    ->toggleable(),
            ])
            ->recordActions([
                self::commandAction(),
                // REQ-0027: reiniciar el equipo (service/app/device) via la cola.
                RestartDeviceAction::make(fn (Device $record): Device => $record),
                // REQ-0028: despertar el equipo por push FCM (ping/restart).
                PushDeviceAction::make(fn (Device $record): Device => $record),
                // VLS-0052/FLX-0038: kill-switch -> baja VLS + Sentinel.
                StopAllDeviceAction::make(fn (Device $record): Device => $record),
                // FLX-0042: diagnostico industrial extendido (get_status/battery/network/...).
                DiagnosticDeviceAction::make(fn (Device $record): Device => $record),
                // FLX-0039: logs de campo -> solicitar / limpiar / descargar el ultimo.
                LogsDeviceActions::requestLogs(fn (Device $record): Device => $record),
                LogsDeviceActions::clearLogs(fn (Device $record): Device => $record),
                LogsDeviceActions::downloadLatest(fn (Device $record): Device => $record),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Encolar un comando al dispositivo (usa la cola de REQ-0004). */
    private static function commandAction(): Action
    {
        return Action::make('comando')
            ->label('Comando')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading('Encolar comando al dispositivo')
            ->schema([
                Select::make('cmd')
                    ->label('Comando')
                    ->options([
                        'snapshot' => 'snapshot',
                        'publish_clip' => 'publish_clip',
                        'delete_clip' => 'delete_clip',
                        'delete_all' => 'delete_all',
                    ])
                    ->required(),
                TextInput::make('ts')->label('ts (para publish_clip)'),
                TextInput::make('file')->label('file (para delete_clip)'),
            ])
            ->action(function (array $data, Device $record): void {
                $cmd = $data['cmd'];

                if ($cmd === 'publish_clip' && blank($data['ts'] ?? null)) {
                    Notification::make()->title('publish_clip requiere ts')->danger()->send();

                    return;
                }
                if ($cmd === 'delete_clip' && blank($data['file'] ?? null)) {
                    Notification::make()->title('delete_clip requiere file')->danger()->send();

                    return;
                }

                $params = [];
                if (filled($data['ts'] ?? null)) {
                    $params['ts'] = $data['ts'];
                }
                if (filled($data['file'] ?? null)) {
                    $params['file'] = $data['file'];
                }

                Command::create([
                    'device_id' => $record->id,
                    'cmd' => $cmd,
                    'params' => $params ?: null,
                    'status' => 'pending',
                ]);

                Notification::make()->title('Comando encolado')->success()->send();
            });
    }
}
