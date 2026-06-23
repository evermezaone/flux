<?php

namespace App\Filament\Actions;

use App\Models\Device;
use App\Models\DeviceLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Acciones de panel para logs de campo (FLX-0039 / VLS-0054):
 *  - requestLogs(): comando `get_logs` -> el equipo arma y sube su paquete de logs a /api/v1/device-logs.
 *  - clearLogs():   comando `reset_logs` -> el equipo limpia sus logs locales (VLS + Sentinel).
 * Reutilizan CommandDispatcher con selector de canal (auto|fcm|poll). El $deviceFrom mapea el record.
 */
class LogsDeviceActions
{
    public static function requestLogs(callable $deviceFrom): Action
    {
        return self::dispatchAction(
            $deviceFrom,
            key: 'solicitar_logs',
            label: 'Solicitar logs',
            icon: Heroicon::OutlinedDocumentArrowUp,
            cmd: 'get_logs',
            heading: 'Solicitar logs del equipo',
            description: 'Pide al equipo que arme y suba su paquete de logs (VLS + Sentinel + estado) para diagnostico.',
        );
    }

    public static function clearLogs(callable $deviceFrom): Action
    {
        return self::dispatchAction(
            $deviceFrom,
            key: 'limpiar_logs',
            label: 'Limpiar logs',
            icon: Heroicon::OutlinedTrash,
            cmd: 'reset_logs',
            heading: 'Limpiar logs del equipo',
            description: 'Ordena al equipo limpiar sus logs locales (VLS y Sentinel).',
        );
    }

    /** Descarga el ultimo paquete de logs subido por el equipo (visible solo si hay alguno). */
    public static function downloadLatest(callable $deviceFrom): Action
    {
        return Action::make('descargar_logs')
            ->label('Último log')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(function ($record) use ($deviceFrom): ?string {
                $device = $deviceFrom($record);
                $log = $device ? DeviceLog::where('device_id', $device->id)->latest('id')->first() : null;

                return $log ? route('device-logs.download', $log) : null;
            })
            ->openUrlInNewTab()
            ->visible(function ($record) use ($deviceFrom): bool {
                $device = $deviceFrom($record);

                return $device !== null && DeviceLog::where('device_id', $device->id)->exists();
            });
    }

    private static function dispatchAction(callable $deviceFrom, string $key, string $label, Heroicon $icon, string $cmd, string $heading, string $description): Action
    {
        return Action::make($key)
            ->label($label)
            ->icon($icon)
            ->requiresConfirmation()
            ->modalHeading($heading)
            ->modalDescription($description)
            ->schema([
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
            ->action(function (array $data, $record) use ($deviceFrom, $cmd, $label): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }

                $channel = $data['channel'] ?? 'auto';
                $res = app(\App\Services\CommandDispatcher::class)->dispatch($device, $cmd, [], $channel);

                $via = $channel === 'poll' ? 'cola (polling)' : ($res['pushed'] ? 'FCM enviado' : 'cola (sin push)');
                Notification::make()
                    ->title("{$label} encolado para {$device->code} — canal: {$channel} · {$via}")
                    ->success()
                    ->send();
            });
    }
}
