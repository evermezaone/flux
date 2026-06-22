<?php

namespace App\Filament\Actions;

use App\Models\Device;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Accion "Detener todo" (FLX-0038 / VLS-0052): kill-switch. Encola/empuja el comando `stop_all`, que en el
 * equipo baja VialSense Y el Sentinel y evita que se auto-relancen (maintenance + cancela alarma de
 * relanzamiento + frena FGS/Watchdog + sale del kiosko + avisa al Sentinel por broadcast). Para reactivar,
 * el operador abre cualquiera de las dos apps en el equipo.
 *
 * @param callable $deviceFrom recibe el $record de la tabla y devuelve el Device asociado.
 */
class StopAllDeviceAction
{
    public static function make(callable $deviceFrom): Action
    {
        return Action::make('detener_todo')
            ->label('Detener todo')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Detener todo (VLS + Sentinel)')
            ->modalDescription('Baja VialSense y el Sentinel en el equipo y evita que se reinicien solos '
                .'(kill-switch). Para reactivarlos hay que ABRIR la app en el equipo. Usalo antes de '
                .'intervenir un equipo en kiosko.')
            ->schema([
                Select::make('channel')
                    ->label('Canal')
                    ->options([
                        'auto' => 'Auto (FCM + cola, sin duplicar)',
                        'fcm' => 'Solo FCM (push instantáneo)',
                        'poll' => 'Solo cola (al consultar comandos)',
                    ])
                    ->default('auto')
                    ->helperText('Por dónde se envía el comando. "Auto" usa push y cola con anti-duplicado.')
                    ->required(),
            ])
            ->action(function (array $data, $record) use ($deviceFrom): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }

                $channel = $data['channel'] ?? 'auto';
                $res = app(\App\Services\CommandDispatcher::class)
                    ->dispatch($device, 'stop_all', [], $channel);

                $via = $channel === 'poll' ? 'cola (polling)' : ($res['pushed'] ? 'FCM enviado' : 'cola (sin push)');
                Notification::make()
                    ->title("Detener todo encolado para {$device->code} — canal: {$channel} · {$via}")
                    ->warning()
                    ->send();
            });
    }
}
