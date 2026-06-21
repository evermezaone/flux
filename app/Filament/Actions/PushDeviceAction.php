<?php

namespace App\Filament\Actions;

use App\Models\Device;
use App\Services\Fcm\FcmSender;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Accion "Despertar (push)" (FLX REQ-0028): envia un push FCM al equipo para forzar una accion
 * (ping/restart) aunque no este consultando la cola (equipo colgado). Reutilizable desde Dispositivos
 * y desde el semaforo de Salud.
 *
 * @param callable $deviceFrom recibe el $record de la tabla y devuelve el Device asociado.
 */
class PushDeviceAction
{
    public static function make(callable $deviceFrom): Action
    {
        return Action::make('despertar')
            ->label('Despertar (push)')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Enviar push al equipo (FCM)')
            ->modalDescription('Despierta al equipo aunque no esté consultando la cola.')
            ->schema([
                Select::make('action')
                    ->label('Acción')
                    ->options(['ping' => 'Forzar chequeo (ping)', 'restart' => 'Reiniciar'])
                    ->default('ping')
                    ->live()
                    ->required(),
                Select::make('level')
                    ->label('Nivel')
                    ->options(['service' => 'Captura/servicio', 'app' => 'App completa', 'device' => 'Teléfono'])
                    ->default('app')
                    ->visible(fn (callable $get): bool => $get('action') === 'restart'),
            ])
            ->action(function (array $data, $record) use ($deviceFrom): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }
                if (blank($device->fcm_token)) {
                    Notification::make()->title("El equipo {$device->code} aún no registró su token FCM")->warning()->send();

                    return;
                }

                $payload = ['action' => $data['action']];
                if ($data['action'] === 'restart') {
                    $payload['level'] = $data['level'] ?? 'app';
                }

                $ok = app(FcmSender::class)->send($device->fcm_token, $payload);

                if ($ok) {
                    Notification::make()->title("Push enviado a {$device->code} ({$data['action']})")->success()->send();
                } else {
                    // token desregistrado: limpiarlo para que el equipo lo vuelva a registrar.
                    $device->forceFill(['fcm_token' => null])->save();
                    Notification::make()->title('Token FCM inválido (se limpió). El equipo debe re-registrarse.')->danger()->send();
                }
            });
    }
}
