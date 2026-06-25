<?php

namespace App\Filament\Resources\Devices\Pages;

use App\Filament\Actions\DiagnosticDeviceAction;
use App\Filament\Actions\LogsDeviceActions;
use App\Filament\Actions\MaintenanceDeviceAction;
use App\Filament\Actions\PushDeviceAction;
use App\Filament\Actions\RestartDeviceAction;
use App\Filament\Actions\ResumeDeviceAction;
use App\Filament\Actions\StabilityDeviceActions;
use App\Filament\Actions\StopAllDeviceAction;
use App\Filament\Resources\Devices\DeviceResource;
use App\Models\DeviceLog;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Central device console: operation summary, health, commands, logs, media and telemetry.
 */
class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.resources.devices.pages.view-device';

    public function getTitle(): string
    {
        return 'Device '.$this->record->code;
    }

    protected function getHeaderActions(): array
    {
        $d = fn () => $this->record;

        return [
            RestartDeviceAction::make($d),
            PushDeviceAction::make($d),
            ResumeDeviceAction::make($d),
            ActionGroup::make([
                StopAllDeviceAction::make($d),
                DiagnosticDeviceAction::make($d),
                StabilityDeviceActions::requestDiagnostics($d),
                LogsDeviceActions::requestLogs($d),
                LogsDeviceActions::downloadLatest($d),
                LogsDeviceActions::clearLogs($d),
                StabilityDeviceActions::resetDiagnostics($d),
                MaintenanceDeviceAction::make($d),
            ])
                ->label('Mas acciones')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }

    protected function getViewData(): array
    {
        $device = $this->record->loadMissing([
            'site',
            'health',
            'supervision',
            'requirementState',
            'stabilityState',
        ]);

        $commands = $device->commands()->latest('id')->limit(20)->get();
        $lastLog = DeviceLog::where('device_id', $device->id)->latest('reported_at')->first();
        $media = $device->media()->where('available', true)->latest('ts_start')->limit(12)->get();
        $telemetry = $device->telemetry()->latest('ts')->limit(8)->get();
        $stabilityEvents = $device->stabilityEvents()->latest('occurred_at')->limit(10)->get();

        return [
            'device' => $device,
            'health' => $device->health,
            'supervision' => $device->supervision,
            'requirements' => $device->requirementState,
            'stability' => $device->stabilityState,
            'stabilityEvents' => $stabilityEvents,
            'commands' => $commands,
            'lastLog' => $lastLog,
            'media' => $media,
            'telemetry' => $telemetry,
        ];
    }
}
