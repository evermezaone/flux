<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Actions\DiagnosticDeviceAction;
use App\Filament\Actions\LogsDeviceActions;
use App\Filament\Actions\PushDeviceAction;
use App\Filament\Actions\RestartDeviceAction;
use App\Filament\Actions\ResumeDeviceAction;
use App\Filament\Actions\StopAllDeviceAction;
use App\Filament\Resources\Devices\DeviceResource;
use App\Models\DeviceLog;
use Filament\Resources\Pages\ViewRecord;

/**
 * FLX-0057: ficha central de gestion de un dispositivo. Punto unico de operacion: resumen, salud, comandos
 * (acciones en el header, reusando las existentes), historial de comandos, logs, media y telemetria reciente.
 * Tokens/keys ocultos por defecto. Vista propia (Blade) por pestanas, usable en mobile. La abren el listado y
 * el mapa (misma ruta).
 */
class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.resources.devices.pages.view-device';

    public function getTitle(): string
    {
        return 'Ficha · '.$this->record->code;
    }

    /** Comandos disponibles desde la ficha (reusan las acciones + confirmaciones existentes). */
    protected function getHeaderActions(): array
    {
        $d = fn () => $this->record;

        return [
            RestartDeviceAction::make($d),
            PushDeviceAction::make($d),
            StopAllDeviceAction::make($d),
            ResumeDeviceAction::make($d),
            DiagnosticDeviceAction::make($d),
            LogsDeviceActions::requestLogs($d),
            LogsDeviceActions::clearLogs($d),
            LogsDeviceActions::downloadLatest($d),
        ];
    }

    protected function getViewData(): array
    {
        $device = $this->record->loadMissing(['site', 'health']);

        $commands = $device->commands()->latest('id')->limit(20)->get();
        $lastLog = DeviceLog::where('device_id', $device->id)->latest('reported_at')->first();
        $media = $device->media()->where('available', true)->latest('ts_start')->limit(12)->get();
        $telemetry = $device->telemetry()->latest('ts')->limit(8)->get();

        return [
            'device' => $device,
            'health' => $device->health,
            'commands' => $commands,
            'lastLog' => $lastLog,
            'media' => $media,
            'telemetry' => $telemetry,
        ];
    }
}
