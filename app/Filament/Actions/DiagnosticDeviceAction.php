<?php

namespace App\Filament\Actions;

use App\Models\Device;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Accion "Diagnostico" (FLX-0042): pide al equipo un diagnostico industrial extendido (VLS-0060) por el
 * canal elegido. El resultado vuelve por ack (visible en la tabla de Comandos) o, si es grande, como
 * paquete en device_logs (FLX-0039, descargable). Reutiliza CommandDispatcher; no duplica vistas.
 *
 * @param callable $deviceFrom recibe el $record y devuelve el Device asociado.
 */
class DiagnosticDeviceAction
{
    /** Diagnosticos disponibles (clave = comando que ejecuta el equipo). */
    private const DIAGS = [
        'get_status' => 'Estado general',
        'get_foreground_state' => 'Estado de primer plano (foreground)',
        'get_battery' => 'Batería / energía',
        'get_network' => 'Red',
        'get_permissions' => 'Permisos críticos',
        'get_device_policy' => 'Device Owner / kiosk / política',
        'get_apps' => 'Apps instaladas',
    ];

    public static function make(callable $deviceFrom): Action
    {
        return Action::make('diagnostico')
            ->label('Diagnóstico')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('info')
            ->modalHeading('Pedir diagnóstico al equipo')
            ->modalDescription('Solicita un diagnóstico industrial extendido. El resultado vuelve por el ack '
                .'(tabla Comandos) o como paquete descargable en logs si es grande.')
            ->schema([
                Select::make('diag')
                    ->label('Diagnóstico')
                    ->options(self::DIAGS)
                    ->default('get_status')
                    ->required(),
                Select::make('channel')
                    ->label('Canal')
                    ->options([
                        'auto' => 'Auto (FCM + cola, sin duplicar)',
                        'fcm' => 'Solo FCM (push instantáneo)',
                        'poll' => 'Solo cola (al consultar comandos)',
                    ])
                    ->default('auto')
                    ->required(),
            ])
            ->action(function (array $data, $record) use ($deviceFrom): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }

                $cmd = $data['diag'] ?? 'get_status';
                $channel = $data['channel'] ?? 'auto';
                $res = app(\App\Services\CommandDispatcher::class)->dispatch($device, $cmd, [], $channel);

                $via = $channel === 'poll' ? 'cola (polling)' : ($res['pushed'] ? 'FCM enviado' : 'cola (sin push)');
                Notification::make()
                    ->title("Diagnóstico {$cmd} solicitado a {$device->code} — canal: {$channel} · {$via}")
                    ->success()
                    ->send();
            });
    }
}
