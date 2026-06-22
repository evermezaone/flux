<?php

namespace App\Filament\Resources\DeviceHealthResource\Tables;

use App\Filament\Actions\MaintenanceDeviceAction;
use App\Filament\Actions\PushDeviceAction;
use App\Filament\Actions\RestartDeviceAction;
use App\Models\DeviceHealth;
use Filament\Tables\Columns\IconColumn;
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

                // REQ-0031: supervivencia del equipo dedicado.
                IconColumn::make('requires_intervention')
                    ->label('Requiere interv.')
                    ->boolean()
                    ->state(fn (DeviceHealth $r): bool => (bool) ($r->device_metrics['requires_intervention'] ?? false)),
                TextColumn::make('sentinel')
                    ->label('Sentinel')
                    ->state(fn (DeviceHealth $r): string => self::sentinel($r))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('device_owner')
                    ->label('Device Owner / Kiosk')
                    ->state(fn (DeviceHealth $r): string => self::deviceOwner($r))
                    ->toggleable(),
                // VLS-0042: capacidades de reinicio separadas (app/service NO requieren Device Owner).
                TextColumn::make('restart_caps')
                    ->label('Reinicio disponible')
                    ->state(fn (DeviceHealth $r): string => self::restartCaps($r))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('recovery')
                    ->label('Última recuperación')
                    ->state(fn (DeviceHealth $r): string => self::recovery($r))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('ultimo_crash')
                    ->label('Último crash')
                    ->state(fn (DeviceHealth $r): string => self::lastCrash($r))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                // REQ-0027: reiniciar directo desde el semaforo (el record es un DeviceHealth).
                RestartDeviceAction::make(fn (DeviceHealth $record) => $record->device),
                // REQ-0028: despertar por push FCM desde el semaforo (util si esta offline/colgado).
                PushDeviceAction::make(fn (DeviceHealth $record) => $record->device),
                // REQ-0031: mantenimiento (pausar auto-recuperacion / limpiar contadores).
                MaintenanceDeviceAction::make(fn (DeviceHealth $record) => $record->device),
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

    /** Estado del VlsSentinel reportado en device_metrics.sentinel (REQ-0031/0034). */
    private static function sentinel(DeviceHealth $r): string
    {
        $s = $r->device_metrics['sentinel'] ?? null;
        if (! is_array($s)) {
            return 'sin Sentinel';
        }
        $action = $s['last_sentinel_action'] ?? '—';
        $h = $s['launch_count_hour'] ?? '0';
        $d = $s['launch_count_day'] ?? '0';

        return "vivo · relanzamientos h/d: {$h}/{$d} · {$action}";
    }

    /** Device Owner / kiosko (REQ-0031/0035). */
    private static function deviceOwner(DeviceHealth $r): string
    {
        $o = $r->device_metrics['device_owner'] ?? null;
        if (! is_array($o)) {
            return '—';
        }
        $owner = ($o['device_owner_available'] ?? false) ? 'DO sí' : 'DO no';
        $kiosk = ($o['kiosk_active'] ?? false) ? 'kiosk on' : 'kiosk off';
        $reboot = ($o['reboot_available'] ?? false) ? 'reboot sí' : 'reboot no';

        return "{$owner} · {$kiosk} · {$reboot}";
    }

    /**
     * VLS-0042: capacidades de reinicio SEPARADAS por nivel. El reinicio de APP no requiere Device Owner
     * (solo alarmas exactas); solo el reboot del TELÉFONO requiere Device Owner.
     */
    private static function restartCaps(DeviceHealth $r): string
    {
        $caps = $r->device_metrics['restart_caps'] ?? null;
        $owner = $r->device_metrics['device_owner'] ?? null;

        if (! is_array($caps) && ! is_array($owner)) {
            return '—';
        }

        // Obs 187: el estado de cada nivel se LEE del heartbeat (no se hardcodea). service desde
        // restart_caps.service_restart_available; app desde app_restart_available; reboot desde device_owner.
        $service = is_array($caps)
            ? (($caps['service_restart_available'] ?? false) ? 'service sí' : 'service NO')
            : 'service ?';
        $app = is_array($caps)
            ? (($caps['app_restart_available'] ?? false) ? 'app sí' : 'app NO (falta alarmas exactas)')
            : 'app ?';
        $reboot = is_array($owner)
            ? (($owner['reboot_available'] ?? false) ? 'reboot sí' : 'reboot NO (sin Device Owner)')
            : 'reboot ?';

        return "{$service} · {$app} · {$reboot}";
    }

    /** Ultima recuperacion del orquestador (REQ-0031/0036). */
    private static function recovery(DeviceHealth $r): string
    {
        $rec = $r->device_metrics['recovery'] ?? null;
        if (! is_array($rec)) {
            return '—';
        }
        $h = $rec['recovery_count_hour'] ?? '0';
        $d = $rec['recovery_count_day'] ?? '0';
        $last = $rec['last_recovery_action'] ?? null;
        $desc = is_array($last) ? (($last['source'] ?? '?') . '/' . ($last['level'] ?? '?') . ' · ' . ($last['result'] ?? '')) : '—';

        return "h/d: {$h}/{$d} · {$desc}";
    }

    /** Ultimo crash (REQ-0031/0027). */
    private static function lastCrash(DeviceHealth $r): string
    {
        $c = $r->device_metrics['last_crash'] ?? null;
        if (! is_array($c)) {
            return '—';
        }
        $summary = $c['summary'] ?? '?';
        $ts = $c['ts'] ?? '';
        $rc = $c['recent_count'] ?? null;

        return $summary . ($rc !== null ? " (x{$rc}/h)" : '') . ($ts ? " · {$ts}" : '');
    }
}
