<?php

namespace App\Filament\Pages;

use App\Models\Command;
use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\GlobalSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Gestion central de configuracion (FLX REQ-0020): edita la config GLOBAL (todos) y la POR EQUIPO,
 * y al guardar encola el comando `config_update` para que los equipos re-lean su config efectiva.
 */
class Configuracion extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Configuración';

    protected static ?string $navigationLabel = 'Configuración';

    protected string $view = 'filament.pages.configuracion';

    public ?array $data = [];

    private const TYPES = ['string' => 'string', 'int' => 'int', 'bool' => 'bool', 'json' => 'json'];

    public function mount(): void
    {
        $this->form->fill([
            'global' => GlobalSetting::orderBy('key')->get(['key', 'value', 'type'])->toArray(),
            'device_id' => null,
            'device' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('global')
                    ->label('Configuración GLOBAL (aplica a todos los equipos)')
                    ->schema([
                        TextInput::make('key')->label('Clave')->required()->maxLength(80),
                        TextInput::make('value')->label('Valor'),
                        Select::make('type')->label('Tipo')->options(self::TYPES)->default('string'),
                    ])
                    ->columns(3)
                    ->addActionLabel('Agregar global'),

                Select::make('device_id')
                    ->label('Equipo (para configuración particular)')
                    ->options(fn () => Device::orderBy('code')->pluck('code', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $set('device', $state
                            ? DeviceSetting::where('device_id', $state)->orderBy('key')->get(['key', 'value', 'type'])->toArray()
                            : []);
                    }),

                Repeater::make('device')
                    ->label('Configuración POR EQUIPO (pisa a la global)')
                    ->schema([
                        TextInput::make('key')->label('Clave')->required()->maxLength(80),
                        TextInput::make('value')->label('Valor'),
                        Select::make('type')->label('Tipo')->options(self::TYPES)->default('string'),
                    ])
                    ->columns(3)
                    ->addActionLabel('Agregar por equipo')
                    ->visible(fn (callable $get): bool => filled($get('device_id'))),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardarGlobal')
                ->label('Guardar global y enviar a todos')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->action('saveGlobal'),
            Action::make('guardarDevice')
                ->label('Guardar equipo y enviar')
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->visible(fn (): bool => filled($this->data['device_id'] ?? null))
                ->action('saveDevice'),
        ];
    }

    /** Sincroniza las settings globales y encola config_update a TODOS los equipos. */
    public function saveGlobal(): void
    {
        $rows = $this->data['global'] ?? [];
        $keys = [];
        foreach ($rows as $r) {
            $key = trim($r['key'] ?? '');
            if ($key === '') {
                continue;
            }
            GlobalSetting::updateOrCreate(['key' => $key], ['value' => $r['value'] ?? null, 'type' => $r['type'] ?? 'string']);
            $keys[] = $key;
        }
        GlobalSetting::whereNotIn('key', $keys ?: ['__none__'])->delete();

        $this->pushConfigUpdate(Device::pluck('id')->all());

        Notification::make()->title('Config global guardada y enviada a todos los equipos')->success()->send();
    }

    /** Sincroniza las settings del equipo seleccionado y encola config_update a ese equipo. */
    public function saveDevice(): void
    {
        $deviceId = $this->data['device_id'] ?? null;
        if (! $deviceId) {
            return;
        }
        $rows = $this->data['device'] ?? [];
        $keys = [];
        foreach ($rows as $r) {
            $key = trim($r['key'] ?? '');
            if ($key === '') {
                continue;
            }
            DeviceSetting::updateOrCreate(
                ['device_id' => $deviceId, 'key' => $key],
                ['value' => $r['value'] ?? null, 'type' => $r['type'] ?? 'string']
            );
            $keys[] = $key;
        }
        DeviceSetting::where('device_id', $deviceId)->whereNotIn('key', $keys ?: ['__none__'])->delete();

        $this->pushConfigUpdate([$deviceId]);

        Notification::make()->title('Config del equipo guardada y enviada')->success()->send();
    }

    /** Encola un comando config_update por cada device id (reutiliza la cola + trazabilidad). */
    private function pushConfigUpdate(array $deviceIds): void
    {
        foreach ($deviceIds as $id) {
            $command = Command::create(['device_id' => $id, 'cmd' => 'config_update', 'status' => 'pending']);
            $command->logEvent('created');
        }
    }
}
