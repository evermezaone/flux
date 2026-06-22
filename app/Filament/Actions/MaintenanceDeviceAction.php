<?php

namespace App\Filament\Actions;

use App\Models\Command;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Acciones de mantenimiento del equipo dedicado (FLX REQ-0031): limpiar contadores de recuperacion
 * (anti-loop) y activar/desactivar modo mantenimiento (pausa la auto-recuperacion). Encola el comando
 * por la cola (el equipo lo ejecuta: VLS clear_recovery / maintenance).
 *
 * @param callable $deviceFrom recibe el $record y devuelve el Device.
 */
class MaintenanceDeviceAction
{
    public static function make(callable $deviceFrom): Action
    {
        return Action::make('mantenimiento')
            ->label('Mantenimiento')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->requiresConfirmation()
            ->modalHeading('Mantenimiento del equipo')
            ->schema([
                Select::make('op')
                    ->label('Operación')
                    ->options([
                        'clear_recovery' => 'Limpiar contadores de recuperación (reset anti-loop)',
                        'maintenance_on' => 'Activar modo mantenimiento (pausar auto-recuperación)',
                        'maintenance_off' => 'Salir de modo mantenimiento',
                    ])
                    ->required(),
            ])
            ->action(function (array $data, $record) use ($deviceFrom): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }

                [$cmd, $params] = match ($data['op']) {
                    'clear_recovery' => ['clear_recovery', null],
                    'maintenance_on' => ['maintenance', ['enabled' => true]],
                    'maintenance_off' => ['maintenance', ['enabled' => false]],
                    default => ['clear_recovery', null],
                };

                $command = Command::create([
                    'device_id' => $device->id,
                    'cmd' => $cmd,
                    'params' => $params,
                    'status' => 'pending',
                ]);
                $command->logEvent('created');

                Notification::make()->title("'{$data['op']}' encolado para {$device->code}")->success()->send();
            });
    }
}
