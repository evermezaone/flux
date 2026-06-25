<?php

namespace App\Filament\Actions;

use App\Models\Device;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Accion "Reanudar" (FLX-0053 / VLS-0084): contraparte remota de "Detener todo". Envia el comando `resume`
 * por FCM (push) -> un equipo detenido NO consulta la cola, pero FCM arranca el proceso. En el equipo,
 * `resume` limpia el halt/maintenance, reinicia el foreground service (health server + watchdog) y avisa al
 * Sentinel para que vuelva a vigilar y traiga VLS al frente -> operacion plena, sin tocar el telefono.
 *
 * @param callable $deviceFrom recibe el $record de la tabla y devuelve el Device asociado.
 */
class ResumeDeviceAction
{
    public static function make(callable $deviceFrom): Action
    {
        return Action::make('reanudar')
            ->label('Reanudar')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Reanudar (VLS + Sentinel)')
            ->modalDescription('Saca al equipo del estado "Detener todo" y lo vuelve a operacion plena '
                .'(deteccion + watchdog + Sentinel) de forma remota, sin tocar el telefono. Se envia por FCM '
                .'porque un equipo detenido no consulta la cola.')
            ->action(function ($record) use ($deviceFrom): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }

                // El dispatcher fuerza canal FCM para 'resume' (poll no llega a un equipo detenido).
                $res = app(\App\Services\CommandDispatcher::class)
                    ->dispatch($device, 'resume', [], 'fcm');

                $via = $res['pushed'] ? 'FCM enviado' : 'sin token FCM (no se pudo empujar)';
                Notification::make()
                    ->title("Reanudar enviado a {$device->code} · {$via}")
                    ->{$res['pushed'] ? 'success' : 'warning'}()
                    ->send();
            });
    }
}
