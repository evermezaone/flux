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
                .'Solo "Teléfono" (reboot) requiere Device Owner + reinicio habilitado por config. '
                .'IMPORTANTE: el reinicio de "Teléfono" se envía SIEMPRE por FCM (el polling NO recupera '
                .'un equipo trabado), y no queda pendiente en cola para no re-ejecutarse tras el reboot.')
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
                Select::make('channel')
                    ->label('Canal')
                    ->options([
                        'auto' => 'Auto (FCM + cola, sin duplicar)',
                        'fcm' => 'Solo FCM (push instantáneo)',
                        'poll' => 'Solo cola (al consultar comandos)',
                    ])
                    ->default('auto')
                    ->helperText('Por dónde se envía el comando (para App/Servicio). "Auto" usa push y cola '
                        .'con anti-duplicado. Nota: para "Teléfono" (reboot) se fuerza FCM y se ignora esta '
                        .'selección — el polling no sirve para un equipo trabado.')
                    ->required(),
            ])
            ->action(function (array $data, $record) use ($deviceFrom): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }

                $res = app(\App\Services\CommandDispatcher::class)
                    ->dispatch($device, 'restart', ['level' => $data['level']], $data['channel'] ?? 'auto');

                // FLX-0040: el canal REAL puede diferir del elegido (device -> forzado a fcm).
                $channel = $res['command']->channel;
                $via = $channel === 'poll' ? 'cola (polling)' : ($res['pushed'] ? 'FCM enviado' : 'cola (sin push)');
                Notification::make()
                    ->title("Reinicio ({$data['level']}) encolado para {$device->code} — canal: {$channel} · {$via}")
                    ->success()
                    ->send();
            });
    }
}
