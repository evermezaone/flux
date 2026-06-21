<?php

namespace App\Filament\Resources\DeviceHealthResource\Tables;

use App\Filament\Actions\PushDeviceAction;
use App\Filament\Actions\RestartDeviceAction;
use App\Models\DeviceHealth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Semaforo de salud por equipo (FLX REQ-0026).
 */
class DeviceHealthTable
{
    public static function configure(Table $table): Table
    {
        $offline = (int) config('health.offline_minutes', 5);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['device.site']))
            ->defaultSort('reported_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('device.code')->label('Equipo')->searchable(),
                TextColumn::make('device.site.code')->label('Cruce')->searchable(),

                // Estado efectivo (considera offline por antiguedad del latido).
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (DeviceHealth $r): string => $r->effectiveStatus($offline))
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'warn' => 'warning',
                        'fail' => 'danger',
                        'offline' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('subsistemas')
                    ->label('Subsistemas en falla')
                    ->state(fn (DeviceHealth $r): string => self::failingSubsystems($r))
                    ->wrap(),

                TextColumn::make('reported_at')
                    ->label('Último latido')
                    ->since()
                    ->sortable(),

                TextColumn::make('app_version')->label('App')->toggleable(),
                TextColumn::make('uptime_s')->label('Uptime (s)')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bateria')
                    ->label('Bat. %')
                    ->state(fn (DeviceHealth $r): ?int => $r->device_metrics['battery_pct'] ?? null)
                    ->toggleable(),
                // Obs 170: ultimo reinicio reportado por el equipo (motivo/nivel/resultado).
                TextColumn::make('ultimo_reinicio')
                    ->label('Último reinicio')
                    ->state(fn (DeviceHealth $r): string => self::lastRestart($r))
                    ->toggleable(),
            ])
            ->recordActions([
                // REQ-0027: reiniciar directo desde el semaforo (el record es un DeviceHealth).
                RestartDeviceAction::make(fn (DeviceHealth $record) => $record->device),
                // REQ-0028: despertar por push FCM desde el semaforo (util si esta offline/colgado).
                PushDeviceAction::make(fn (DeviceHealth $record) => $record->device),
            ]);
    }

    /** Resumen de los subsistemas que no estan OK (para la columna). */
    private static function failingSubsystems(DeviceHealth $r): string
    {
        $subs = $r->subsystems ?? [];
        $bad = [];
        foreach ($subs as $name => $s) {
            $status = is_array($s) ? ($s['status'] ?? 'ok') : 'ok';
            if ($status !== 'ok') {
                $detail = is_array($s) ? ($s['detail'] ?? '') : '';
                $bad[] = "{$name}: {$detail}";
            }
        }

        return empty($bad) ? '—' : implode(' · ', $bad);
    }

    /** Ultimo reinicio reportado por el equipo (device_metrics.last_restart) (Obs 170). */
    private static function lastRestart(DeviceHealth $r): string
    {
        $lr = $r->device_metrics['last_restart'] ?? null;
        if (! is_array($lr)) {
            return '—';
        }
        $level = $lr['level'] ?? '?';
        $reason = $lr['reason'] ?? '?';
        $ok = ($lr['ok'] ?? false) ? 'ok' : 'falló';
        $ts = $lr['ts'] ?? '';

        return "{$level}/{$reason} · {$ok}" . ($ts ? " · {$ts}" : '');
    }
}
