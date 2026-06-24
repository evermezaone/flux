<?php

namespace App\Filament\Actions;

use App\Models\Device;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * FLX-0048/0050: acciones manuales de estabilidad desde el panel:
 *  - requestDiagnostics(): comando get_diagnostics -> el equipo arma y sube su paquete diagnostico.
 *  - resetDiagnostics():   comando reset_diagnostics -> limpia paquetes manteniendo contadores agregados.
 */
class StabilityDeviceActions
{
    public static function requestDiagnostics(callable $deviceFrom): Action
    {
        return self::dispatch($deviceFrom, 'pedir_diagnostico', 'Pedir diagnóstico', Heroicon::OutlinedClipboardDocumentList,
            'get_diagnostics', 'Pedir diagnóstico de estabilidad', 'El equipo arma y sube su paquete diagnóstico (estabilidad + estado + logs).');
    }

    public static function resetDiagnostics(callable $deviceFrom): Action
    {
        return self::dispatch($deviceFrom, 'reset_diagnostico', 'Reset diagnósticos', Heroicon::OutlinedArrowPath,
            'reset_diagnostics', 'Resetear diagnósticos', 'Limpia los paquetes diagnósticos del equipo (mantiene los contadores agregados).');
    }

    private static function dispatch(callable $deviceFrom, string $key, string $label, Heroicon $icon, string $cmd, string $heading, string $description): Action
    {
        return Action::make($key)
            ->label($label)
            ->icon($icon)
            ->requiresConfirmation()
            ->modalHeading($heading)
            ->modalDescription($description)
            ->action(function ($record) use ($deviceFrom, $cmd, $label): void {
                /** @var Device|null $device */
                $device = $deviceFrom($record);
                if (! $device) {
                    Notification::make()->title('No se encontró el dispositivo')->danger()->send();

                    return;
                }
                $res = app(\App\Services\CommandDispatcher::class)->dispatch($device, $cmd, [], 'auto');
                $via = ($res['pushed'] ?? false) ? 'FCM enviado' : 'cola (sin push)';
                Notification::make()->title("{$label} encolado para {$device->code} · {$via}")->success()->send();
            });
    }
}
