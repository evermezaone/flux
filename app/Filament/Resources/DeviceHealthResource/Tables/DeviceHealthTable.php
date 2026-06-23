<?php

namespace App\Filament\Resources\DeviceHealthResource\Tables;

use App\Filament\Actions\DiagnosticDeviceAction;
use App\Filament\Actions\LogsDeviceActions;
use App\Filament\Actions\MaintenanceDeviceAction;
use App\Filament\Actions\PushDeviceAction;
use App\Filament\Actions\RestartDeviceAction;
use App\Filament\Actions\StopAllDeviceAction;
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

                // FLX-0035: distinguir VLS "al frente" de "vivo en background". health ok + background
                // NO es una caida, pero el equipo dedicado no esta en modo operativo pleno.
                TextColumn::make('app_foreground')
                    ->label('VLS al frente')
                    ->badge()
                    ->state(fn (DeviceHealth $r): string => self::appForeground($r))
                    ->color(fn (string $state): string => match ($state) {
                        'al frente' => 'success',
                        'no (background)' => 'warning',
                        default => 'gray',
                    }),

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
                // FLX-0042: indicadores industriales (perfil, keyguard, SLA foreground, permisos criticos).
                // Se completan cuando el equipo los reporta (VLS-0057/0058/0060); graceful si faltan.
                TextColumn::make('industrial')
                    ->label('Diagnóstico industrial')
                    ->state(fn (DeviceHealth $r): string => self::industrial($r))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                // FLX-0044: prerequisitos operativos (de operational_requirements). Alerta visible con color.
                TextColumn::make('prerequisitos')
                    ->label('Prerequisitos')
                    ->state(fn (DeviceHealth $r): string => self::requirements($r))
                    ->badge()
                    ->color(fn (DeviceHealth $r): string => self::requirementsSeverity($r))
                    ->wrap(),
                // FLX-0043: energia (bateria vs umbrales) + mantenimiento (edad + revision/reemplazo).
                // Codex: color por severidad para que la alerta sea visible sin leer todo el texto.
                TextColumn::make('mantenimiento')
                    ->label('Energía / mantenimiento')
                    ->state(fn (DeviceHealth $r): string => self::maintenance($r))
                    ->badge()
                    ->color(fn (DeviceHealth $r): string => self::maintenanceSeverity($r))
                    ->wrap()
                    ->toggleable(),
            ])
            ->recordActions([
                // REQ-0027: reiniciar directo desde el semaforo (el record es un DeviceHealth).
                RestartDeviceAction::make(fn (DeviceHealth $record) => $record->device),
                // REQ-0028: despertar por push FCM desde el semaforo (util si esta offline/colgado).
                PushDeviceAction::make(fn (DeviceHealth $record) => $record->device),
                // VLS-0052/FLX-0038: kill-switch -> baja VLS + Sentinel.
                StopAllDeviceAction::make(fn (DeviceHealth $record) => $record->device),
                // FLX-0042: diagnostico industrial extendido.
                DiagnosticDeviceAction::make(fn (DeviceHealth $record) => $record->device),
                // FLX-0039: logs de campo.
                LogsDeviceActions::requestLogs(fn (DeviceHealth $record) => $record->device),
                LogsDeviceActions::clearLogs(fn (DeviceHealth $record) => $record->device),
                LogsDeviceActions::downloadLatest(fn (DeviceHealth $record) => $record->device),
                // REQ-0031: mantenimiento (pausar auto-recuperacion / limpiar contadores).
                MaintenanceDeviceAction::make(fn (DeviceHealth $record) => $record->device),
            ]);
    }

    /**
     * FLX-0035: estado de primer plano de VLS. 'sí' = al frente; 'no (background)' = vivo pero en
     * background (no es caida); 'desconocido' = la APK no reporta el campo (version vieja).
     */
    private static function appForeground(DeviceHealth $r): string
    {
        $fg = $r->device_metrics['app_foreground'] ?? null;
        if ($fg === null) {
            return 'desconocido';
        }

        return $fg ? 'al frente' : 'no (background)';
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

    /**
     * FLX-0042: indicadores industriales del heartbeat (perfil, keyguard, SLA foreground, permisos
     * criticos). Graceful: muestra solo lo que el equipo reporta (VLS-0057/0058/0060); '—' si nada.
     * Publico para test focalizado. `industrial_profile` puede venir como BLOQUE (VLS-0058) o bool/string.
     */
    public static function industrial(DeviceHealth $r): string
    {
        $m = $r->device_metrics ?? [];
        $parts = [];

        if (array_key_exists('industrial_profile', $m)) {
            $ip = $m['industrial_profile'];
            if (is_array($ip)) {
                // VLS-0058: bloque de estado del perfil industrial -> render compacto de los campos clave.
                $flags = [];
                foreach ([
                    'device_owner' => 'DO',
                    'keyguard_disabled' => 'keyguard off',
                    'lock_task_permitted' => 'lock-task ok',
                    'lock_task_active' => 'kiosk activo',
                    'install_restrictions_applied' => 'install restringido',
                    'unknown_sources_restricted' => 'orígenes desc. restringidos',
                ] as $key => $label) {
                    if (array_key_exists($key, $ip)) {
                        $flags[] = $label.': '.($ip[$key] ? 'sí' : 'no');
                    }
                }
                $perfil = empty($flags) ? '?' : implode(', ', $flags);
                if (! empty($ip['profile_last_error'])) {
                    $perfil .= ' · error: '.$ip['profile_last_error'];
                }
                $parts[] = 'perfil: '.$perfil;
            } else {
                // Compat con versiones viejas que reportaran bool/string.
                $parts[] = 'perfil: '.(is_bool($ip) ? ($ip ? 'sí' : 'no') : (string) $ip);
            }
        }
        if (array_key_exists('keyguard_locked', $m)) {
            $parts[] = 'keyguard: '.($m['keyguard_locked'] ? 'bloqueado' : 'desbloqueado');
        }
        if (is_array($m['foreground_sla'] ?? null)) {
            $sla = $m['foreground_sla'];
            $met = $sla['met'] ?? ($sla['ok'] ?? null);
            $secs = $sla['seconds_since_not_foreground'] ?? null;
            $reason = $sla['last_foreground_failure_reason'] ?? null;
            $parts[] = 'foreground SLA: '.($met === null ? '—' : ($met ? 'OK' : 'fuera'))
                .($secs !== null && $secs >= 0 ? " ({$secs}s sin frente)" : '')
                .(! $met && ! empty($reason) ? " · {$reason}" : '');
        }
        if (is_array($m['permissions'] ?? null)) {
            $missing = array_keys(array_filter($m['permissions'], static fn ($v) => $v === false || $v === 'false' || $v === 0 || $v === '0'));
            $parts[] = empty($missing) ? 'permisos: OK' : 'permisos faltan: '.implode(', ', $missing);
        }

        return empty($parts) ? '—' : implode(' · ', $parts);
    }

    /** FLX-0043: energia (bateria vs umbrales) + mantenimiento (edad operativa + recomendacion). */
    private static function maintenance(DeviceHealth $r): string
    {
        $device = $r->device;
        if (! $device) {
            return '—';
        }
        $svc = app(\App\Services\MaintenanceService::class);
        $e = $svc->energyState($device);
        $rec = $svc->recommendation($device);

        $parts = [];
        if ($e['battery_pct'] !== null) {
            $parts[] = "batería {$e['battery_pct']}% ({$e['level']})";
        }
        if ($e['temp_alert']) {
            $parts[] = "temp alta ({$e['temp_c']}°)";
        }
        if ($device->power_source) {
            $parts[] = "fuente: {$device->power_source}";
        }
        if ($rec['age_months'] !== null) {
            $parts[] = "edad: {$rec['age_months']}m";
        }
        if (in_array($rec['status'], ['revision', 'reemplazo'], true)) {
            $parts[] = $rec['text'];
        }

        return empty($parts) ? '—' : implode(' · ', $parts);
    }

    /** FLX-0044: resumen de prerequisitos operativos (estado + fallos + ultima recuperacion). */
    private static function requirements(DeviceHealth $r): string
    {
        $st = $r->device?->requirementState;
        if (! $st) {
            return '—';
        }
        if ($st->ok && $st->warning_count === 0) {
            $s = 'OK';
        } else {
            $parts = [];
            if ($st->critical_count > 0) {
                $parts[] = "{$st->critical_count} crítico(s)";
            }
            if ($st->warning_count > 0) {
                $parts[] = "{$st->warning_count} warning(s)";
            }
            $failed = collect($st->failures ?? [])->pluck('check')->implode(', ');
            $s = implode(' · ', $parts).($failed ? " · {$failed}" : '');
        }
        if ($st->last_recovery_at) {
            $s .= ' · últ. recup.: '.$st->last_recovery_at->diffForHumans();
        }

        return $s;
    }

    /** FLX-0044: severidad de la columna de prerequisitos para color/badge. */
    public static function requirementsSeverity(DeviceHealth $r): string
    {
        $st = $r->device?->requirementState;
        if (! $st) {
            return 'gray';
        }
        if ($st->critical_count > 0) {
            return 'danger';
        }

        return $st->warning_count > 0 ? 'warning' : 'success';
    }

    /** FLX-0043 (Codex): severidad de la columna de mantenimiento para color/badge. */
    public static function maintenanceSeverity(DeviceHealth $r): string
    {
        $device = $r->device;
        if (! $device) {
            return 'gray';
        }
        $svc = app(\App\Services\MaintenanceService::class);
        $e = $svc->energyState($device);
        $rec = $svc->recommendation($device);

        if (in_array($e['level'], ['critical', 'shutdown'], true) || $e['temp_alert'] || $rec['status'] === 'reemplazo') {
            return 'danger';
        }
        if ($e['level'] === 'warning' || $rec['status'] === 'revision') {
            return 'warning';
        }

        return 'gray';
    }
}
