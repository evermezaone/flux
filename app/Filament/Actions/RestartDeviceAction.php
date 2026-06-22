<?php

namespace App\Filament\Actions;

use App\Models\Command;
use App\Models\Device;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Accion "Reiniciar equipo" (FLX REQ-0027): encola el comando `restart` con el nivel elegido
 * (service/app/device). Reutilizable desde Dispositivos y desde el semaforo de Salud.
 * El equipo lo ejecuta via la cola (VLS-0023). El reboot de telefono requiere Device Owner en el equipo.
 *
 * @param callable $deviceFrom recibe el $record de la tabla y devuelve el Device asociado.
 */
class RestartDeviceAction
{
    public static function make(callable $deviceFrom): Action
    {
        return Action::make('reiniciar')
            ->label('Reiniciar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reiniciar equipo')
            ->modalDescription('Encola un reinicio. "Captura/servicio" y "App" NO requieren Device Owner. '
                .'Solo "Teléfono" (reboot) requiere Device Owner + reinicio habilitado por config.')
            ->schema([
                Select::make('level')
                    ->label('Nivel')
                    ->options([
                        'service' => 'Captura / servicio — sin Device Owner',
                        'app' => 'App completa — sin Device Owner (requiere alarmas exactas en el equipo)',
                        'device' => 'Teléfono / reboot — requiere Device Owner',
                    ])
                    ->default('app')
                    ->helperText('App = reinicia la aplicación (no el teléfono). Teléfono = reinicia el equipo entero (solo Device Owner).')
                    ->required(),
            ])
            ->action(function (array $data, $record) use ($deviceFrom): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }

                $command = Command::create([
                    'device_id' => $device->id,
                    'cmd' => 'restart',
                    'params' => ['level' => $data['level']],
                    'status' => 'pending',
                ]);
                $command->logEvent('created');

                Notification::make()
                    ->title("Reinicio ({$data['level']}) encolado para {$device->code}")
                    ->success()
                    ->send();
            });
    }
}
