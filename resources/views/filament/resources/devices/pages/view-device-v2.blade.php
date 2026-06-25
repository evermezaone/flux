<x-filament-panels::page>
    @php
        $hm = $health?->device_metrics ?? [];
        $site = $device->site;
        $status = $health?->overall ?? 'sin datos';
        $reportedAt = $health?->reported_at;
        $lastSeen = $device->last_seen_at;
        $fg = data_get($hm, 'app_foreground');
        $fgLabel = $fg === null ? 'desconocido' : ($fg ? 'al frente' : 'background');
        $battery = data_get($hm, 'battery_pct');
        $vlsVer = data_get($hm, 'apps.vls.version_name') ?? ($health?->app_version ?? null);
        $vlsCode = data_get($hm, 'apps.vls.version_code');
        $senVer = data_get($hm, 'apps.sentinel.version_name');
        $senCode = data_get($hm, 'apps.sentinel.version_code');
        $maskKey = $device->device_key ? (substr($device->device_key, 0, 3).'***'.substr($device->device_key, -2)) : null;

        $fmt = function ($value) {
            if ($value === null || $value === '') return 'sin datos';
            if (is_bool($value)) return $value ? 'si' : 'no';
            if (is_array($value) || is_object($value)) return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return (string) $value;
        };

        $healthTone = match ($status) {
            'ok' => 'ok',
            'warn' => 'warn',
            'fail' => 'fail',
            default => 'muted',
        };
        $fgTone = $fg === true ? 'ok' : ($fg === false ? 'warn' : 'muted');
        $reqTone = $requirements?->ok === true ? 'ok' : ($requirements ? 'fail' : 'muted');
        $stabilityTone = match ($stability?->stability_status) {
            'ok' => 'ok',
            'warn' => 'warn',
            'critical' => 'fail',
            default => 'muted',
        };

        $tabs = [
            'resumen' => 'Resumen',
            'salud' => 'Salud',
            'comandos' => 'Comandos',
            'logs' => 'Logs',
            'media' => 'Media',
            'telemetria' => 'Telemetria',
        ];
    @endphp

    <style>
        .flx-device { --ink:#111827; --muted:#6b7280; --line:#e5e7eb; --soft:#f9fafb; --panel:#fff; --dark:#111827; }
        .flx-device * { box-sizing: border-box; }
        .flx-shell { display: grid; gap: 18px; }
        .flx-hero { overflow: hidden; border: 1px solid var(--line); border-radius: 10px; background: var(--panel); box-shadow: 0 1px 2px rgba(15,23,42,.06); }
        .flx-hero-top { display: grid; grid-template-columns: minmax(0, 1fr) minmax(360px, 560px); gap: 24px; padding: 24px; color: #fff; background: #0f172a; }
        .flx-eyebrow { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
        .flx-title { margin:0; font-size: 30px; line-height: 1.15; font-weight: 750; letter-spacing: 0; }
        .flx-subtitle { margin-top:6px; color:#cbd5e1; font-size:14px; }
        .flx-pill { display:inline-flex; align-items:center; min-height:24px; padding:3px 9px; border-radius:999px; font-size:12px; font-weight:650; border:1px solid transparent; }
        .flx-pill.ok { color:#166534; background:#dcfce7; border-color:#86efac; }
        .flx-pill.warn { color:#92400e; background:#fef3c7; border-color:#fcd34d; }
        .flx-pill.fail { color:#991b1b; background:#fee2e2; border-color:#fecaca; }
        .flx-pill.muted { color:#374151; background:#f3f4f6; border-color:#e5e7eb; }
        .flx-metrics { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:10px; }
        .flx-metric { border:1px solid rgba(148,163,184,.28); border-radius:8px; padding:12px; background:rgba(255,255,255,.06); min-width:0; }
        .flx-metric-label { color:#94a3b8; font-size:12px; font-weight:650; }
        .flx-metric-value { margin-top:5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#fff; font-size:16px; font-weight:750; }
        .flx-metric-hint { margin-top:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#94a3b8; font-size:12px; }
        .flx-status-row { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-top:1px solid var(--line); }
        .flx-status-card { padding:18px 20px; border-right:1px solid var(--line); min-width:0; }
        .flx-status-card:last-child { border-right:0; }
        .flx-kicker { color:var(--muted); font-size:12px; font-weight:750; text-transform:uppercase; }
        .flx-status-main { margin-top:6px; color:var(--ink); font-size:15px; font-weight:750; }
        .flx-status-sub { margin-top:4px; color:var(--muted); font-size:13px; line-height:1.35; }
        .flx-tabs { display:flex; gap:4px; padding:5px; border:1px solid var(--line); border-radius:10px; background:var(--soft); overflow-x:auto; }
        .flx-tab { appearance:none; border:0; border-radius:7px; background:transparent; color:var(--muted); cursor:pointer; padding:9px 13px; white-space:nowrap; font-size:14px; font-weight:700; }
        .flx-tab.active { background:#fff; color:var(--ink); box-shadow:0 1px 2px rgba(15,23,42,.08); outline:1px solid var(--line); }
        .flx-grid { display:grid; grid-template-columns: 1.35fr .65fr; gap:16px; }
        .flx-grid-2 { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:16px; }
        .flx-card { border:1px solid var(--line); border-radius:10px; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04); min-width:0; }
        .flx-card-head { padding:16px 18px; border-bottom:1px solid var(--line); }
        .flx-card-title { margin:0; color:var(--ink); font-size:15px; font-weight:800; }
        .flx-card-body { padding:16px 18px; }
        .flx-fields { display:grid; gap:0; }
        .flx-field { display:grid; grid-template-columns: 180px minmax(0,1fr); gap:14px; padding:10px 0; border-bottom:1px solid #f1f5f9; }
        .flx-field:last-child { border-bottom:0; }
        .flx-label { color:var(--muted); font-size:12px; font-weight:800; text-transform:uppercase; }
        .flx-value { min-width:0; overflow-wrap:anywhere; color:var(--ink); font-size:14px; }
        .flx-note { color:var(--muted); font-size:14px; line-height:1.5; }
        .flx-list { display:grid; gap:0; }
        .flx-item { padding:14px 18px; border-bottom:1px solid #f1f5f9; }
        .flx-item:last-child { border-bottom:0; }
        .flx-item-top { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; }
        .flx-item-title { color:var(--ink); font-size:14px; font-weight:750; overflow-wrap:anywhere; }
        .flx-item-meta { margin-top:5px; color:var(--muted); font-size:12px; display:flex; flex-wrap:wrap; gap:12px; }
        .flx-empty { padding:22px; text-align:center; color:var(--muted); font-size:14px; }
        .flx-actions-inline { display:flex; gap:8px; flex-wrap:wrap; }
        .flx-link-btn { display:inline-flex; align-items:center; justify-content:center; min-height:34px; padding:7px 12px; border-radius:8px; border:1px solid var(--line); color:var(--ink); background:#fff; font-size:13px; font-weight:700; text-decoration:none; }
        .flx-link-btn.primary { border-color:#2563eb; background:#2563eb; color:#fff; }
        .flx-result { margin-top:10px; border-radius:8px; background:#f8fafc; padding:10px; color:#334155; font-size:12px; line-height:1.45; }
        [x-cloak] { display:none !important; }
        @media (max-width: 1100px) { .flx-hero-top, .flx-grid, .flx-grid-2 { grid-template-columns:1fr; } .flx-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width: 700px) { .flx-status-row { grid-template-columns:1fr; } .flx-status-card { border-right:0; border-bottom:1px solid var(--line); } .flx-status-card:last-child { border-bottom:0; } .flx-field { grid-template-columns:1fr; gap:4px; } .flx-title { font-size:24px; } }
    </style>

    <div class="flx-device" x-data="{ tab: 'resumen' }">
        <div class="flx-shell">
            <section class="flx-hero">
                <div class="flx-hero-top">
                    <div>
                        <div class="flx-eyebrow">
                            <span class="flx-pill {{ $healthTone }}">{{ strtoupper($status) }}</span>
                            <span class="flx-pill {{ $fgTone }}">VLS {{ $fgLabel }}</span>
                            <span class="flx-pill {{ $device->active ? 'ok' : 'muted' }}">{{ $device->active ? 'activo' : 'inactivo' }}</span>
                        </div>
                        <h1 class="flx-title">{{ $device->code }}</h1>
                        <div class="flx-subtitle">{{ $site?->code ?? 'sin sitio' }}@if($device->model) - {{ $device->model }}@endif</div>
                    </div>

                    <div class="flx-metrics">
                        <div class="flx-metric">
                            <div class="flx-metric-label">Ultimo latido</div>
                            <div class="flx-metric-value">{{ $reportedAt ? $reportedAt->diffForHumans() : 'sin datos' }}</div>
                            <div class="flx-metric-hint">{{ $reportedAt?->format('Y-m-d H:i:s') }}</div>
                        </div>
                        <div class="flx-metric">
                            <div class="flx-metric-label">Bateria</div>
                            <div class="flx-metric-value">{{ $battery !== null ? $battery.'%' : 'sin datos' }}</div>
                        </div>
                        <div class="flx-metric">
                            <div class="flx-metric-label">VLS</div>
                            <div class="flx-metric-value">{{ $vlsVer ?: 'sin version' }}</div>
                            <div class="flx-metric-hint">{{ $vlsCode ? 'code '.$vlsCode : '' }}</div>
                        </div>
                        <div class="flx-metric">
                            <div class="flx-metric-label">Sentinel</div>
                            <div class="flx-metric-value">{{ $senVer ?: 'sin version' }}</div>
                            <div class="flx-metric-hint">{{ $senCode ? 'code '.$senCode : '' }}</div>
                        </div>
                    </div>
                </div>

                <div class="flx-status-row">
                    <div class="flx-status-card">
                        <div class="flx-kicker">Supervision remota</div>
                        <div class="flx-status-main">{{ $supervision?->state ?? 'sin estado' }}</div>
                        <div class="flx-status-sub">{{ $supervision?->reason ?? 'sin motivo activo' }}</div>
                    </div>
                    <div class="flx-status-card">
                        <div class="flx-kicker">Prerequisitos</div>
                        <div class="flx-status-main"><span class="flx-pill {{ $reqTone }}">{{ $requirements?->ok === true ? 'OK' : ($requirements ? 'requiere atencion' : 'sin datos') }}</span></div>
                        <div class="flx-status-sub">Criticos: {{ $requirements?->critical_count ?? 0 }} - Warnings: {{ $requirements?->warning_count ?? 0 }}</div>
                    </div>
                    <div class="flx-status-card">
                        <div class="flx-kicker">Estabilidad</div>
                        <div class="flx-status-main"><span class="flx-pill {{ $stabilityTone }}">{{ $stability?->stability_status ?? 'sin datos' }}</span></div>
                        <div class="flx-status-sub">Crash {{ $stability?->crash_count_24h ?? 0 }} - ANR {{ $stability?->anr_count_24h ?? 0 }} - UI {{ $stability?->ui_freeze_count_24h ?? 0 }}</div>
                    </div>
                </div>
            </section>

            <nav class="flx-tabs" aria-label="Device tabs">
                @foreach ($tabs as $key => $label)
                    <button type="button" @click="tab = '{{ $key }}'" :class="{ 'active': tab === '{{ $key }}' }" class="flx-tab">{{ $label }}</button>
                @endforeach
            </nav>

            <section x-show="tab === 'resumen'" x-cloak class="flx-grid">
                <div class="flx-card">
                    <div class="flx-card-head"><h2 class="flx-card-title">Identidad y conectividad</h2></div>
                    <div class="flx-card-body flx-fields">
                        <div class="flx-field"><div class="flx-label">Codigo</div><div class="flx-value">{{ $device->code }}</div></div>
                        <div class="flx-field"><div class="flx-label">Sitio</div><div class="flx-value">{{ $site?->code ?? 'sin sitio' }}</div></div>
                        <div class="flx-field"><div class="flx-label">Modelo</div><div class="flx-value">{{ $device->model ?? 'sin modelo' }}</div></div>
                        <div class="flx-field"><div class="flx-label">Device key</div><div class="flx-value"><code>{{ $maskKey ?: 'sin key' }}</code></div></div>
                        <div class="flx-field"><div class="flx-label">FCM token</div><div class="flx-value"><span class="flx-pill {{ $device->fcm_token ? 'ok' : 'warn' }}">{{ $device->fcm_token ? 'presente' : 'ausente' }}</span></div></div>
                        <div class="flx-field"><div class="flx-label">FCM actualizado</div><div class="flx-value">{{ $device->fcm_token_at ? $device->fcm_token_at->diffForHumans() : 'sin datos' }}</div></div>
                        <div class="flx-field"><div class="flx-label">Ultima comunicacion</div><div class="flx-value">{{ $lastSeen ? $lastSeen->diffForHumans().' ('.$lastSeen->format('Y-m-d H:i:s').')' : 'sin datos' }}</div></div>
                    </div>
                </div>
                <div class="flx-card">
                    <div class="flx-card-head"><h2 class="flx-card-title">Operacion</h2></div>
                    <div class="flx-card-body">
                        <div class="flx-note">Acciones principales arriba: reiniciar, despertar y reanudar. Las acciones de diagnostico, logs, stop, reset y mantenimiento estan agrupadas en el menu Mas acciones.</div>
                    </div>
                </div>
            </section>

            <section x-show="tab === 'salud'" x-cloak class="flx-grid-2">
                <div class="flx-card">
                    <div class="flx-card-head"><h2 class="flx-card-title">Estado operativo</h2></div>
                    <div class="flx-card-body flx-fields">
                        <div class="flx-field"><div class="flx-label">Estado global</div><div class="flx-value">{{ strtoupper($status) }}</div></div>
                        <div class="flx-field"><div class="flx-label">App al frente</div><div class="flx-value">{{ $fgLabel }}</div></div>
                        <div class="flx-field"><div class="flx-label">Red</div><div class="flx-value">{{ $fmt(data_get($hm, 'network.type', data_get($hm, 'network'))) }}</div></div>
                        <div class="flx-field"><div class="flx-label">Sentinel</div><div class="flx-value">{{ $fmt(data_get($hm, 'sentinel.sentinel_watch_status', data_get($hm, 'sentinel_watch_status', data_get($hm, 'sentinel')))) }}</div></div>
                        <div class="flx-field"><div class="flx-label">Device Owner</div><div class="flx-value">{{ $fmt(data_get($hm, 'device_owner.enabled', data_get($hm, 'device_owner'))) }}</div></div>
                        <div class="flx-field"><div class="flx-label">Lock task</div><div class="flx-value">{{ $fmt(data_get($hm, 'lock_task.active', data_get($hm, 'lock_task'))) }}</div></div>
                        <div class="flx-field"><div class="flx-label">Intervencion</div><div class="flx-value">{{ !empty($hm['requires_intervention']) ? 'requerida' : 'no requerida' }}</div></div>
                        <div class="flx-field"><div class="flx-label">Uptime</div><div class="flx-value">{{ $health?->uptime_s ? number_format($health->uptime_s).' s' : 'sin datos' }}</div></div>
                    </div>
                </div>
                <div class="flx-card">
                    <div class="flx-card-head"><h2 class="flx-card-title">Fallas y estabilidad</h2></div>
                    <div class="flx-card-body flx-fields">
                        <div class="flx-field"><div class="flx-label">Ultimo evento</div><div class="flx-value">{{ $stability?->last_stability_event ?? 'sin datos' }}</div></div>
                        <div class="flx-field"><div class="flx-label">Ultimo evento en</div><div class="flx-value">{{ $stability?->last_stability_event_at ? $stability->last_stability_event_at->diffForHumans() : 'sin datos' }}</div></div>
                        <div class="flx-field"><div class="flx-label">UI congelada</div><div class="flx-value">{{ $stability?->ui_frozen === null ? 'sin datos' : ($stability->ui_frozen ? 'si' : 'no') }}</div></div>
                        <div class="flx-field"><div class="flx-label">Ultimo tick UI</div><div class="flx-value">{{ $stability?->ui_last_tick_at ? $stability->ui_last_tick_at->diffForHumans() : 'sin datos' }}</div></div>
                        <div class="flx-field"><div class="flx-label">Recuperacion</div><div class="flx-value">{{ $stability?->last_recovery_action ?? 'sin accion' }}</div></div>
                    </div>
                </div>
                <div class="flx-card" style="grid-column: 1 / -1;">
                    <div class="flx-card-head"><h2 class="flx-card-title">Eventos de estabilidad recientes</h2></div>
                    <div class="flx-list">
                        @forelse ($stabilityEvents as $event)
                            <div class="flx-item">
                                <div class="flx-item-top">
                                    <div class="flx-item-title">{{ $event->event_type }} - {{ $event->severity }}</div>
                                    <div class="flx-item-meta">{{ $event->occurred_at?->format('Y-m-d H:i:s') }}</div>
                                </div>
                                <div class="flx-item-meta">{{ $event->summary ?: 'Sin resumen' }}</div>
                            </div>
                        @empty
                            <div class="flx-empty">Sin eventos de estabilidad recientes.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section x-show="tab === 'comandos'" x-cloak class="flx-card">
                <div class="flx-card-head"><h2 class="flx-card-title">Historial de comandos</h2></div>
                <div class="flx-list">
                    @forelse ($commands as $c)
                        <div class="flx-item">
                            <div class="flx-item-top">
                                <div class="flx-item-title"><code>{{ $c->cmd }}</code></div>
                                <span class="flx-pill {{ $c->status === 'done' ? 'ok' : ($c->status === 'failed' ? 'fail' : ($c->status === 'pending' ? 'warn' : 'muted')) }}">{{ $c->status }}</span>
                            </div>
                            <div class="flx-item-meta">
                                <span>canal: {{ $c->channel }}@if($c->exec_channel && $c->exec_channel !== $c->channel) -> {{ $c->exec_channel }}@endif</span>
                                <span>creado: {{ $c->created_at?->diffForHumans() }}</span>
                                @if($c->picked_at)<span>tomado: {{ $c->picked_at->diffForHumans() }}</span>@endif
                                @if($c->done_at)<span>cerrado: {{ $c->done_at->diffForHumans() }}</span>@endif
                            </div>
                            @if($c->result)<div class="flx-result">{{ \Illuminate\Support\Str::limit($c->result, 260) }}</div>@endif
                        </div>
                    @empty
                        <div class="flx-empty">Sin comandos registrados.</div>
                    @endforelse
                </div>
            </section>

            <section x-show="tab === 'logs'" x-cloak class="flx-grid">
                <div class="flx-card">
                    <div class="flx-card-head"><h2 class="flx-card-title">Ultimo paquete de logs</h2></div>
                    <div class="flx-card-body flx-fields">
                        @if($lastLog)
                            <div class="flx-field"><div class="flx-label">Origen</div><div class="flx-value">{{ $lastLog->source }}</div></div>
                            <div class="flx-field"><div class="flx-label">Resumen</div><div class="flx-value">{{ $lastLog->summary ?: 'sin resumen' }}</div></div>
                            <div class="flx-field"><div class="flx-label">Build</div><div class="flx-value">{{ $lastLog->build ?: 'sin build' }}</div></div>
                            <div class="flx-field"><div class="flx-label">Tamano</div><div class="flx-value">{{ $lastLog->size ? number_format($lastLog->size / 1024, 0).' KB' : 'sin datos' }}</div></div>
                            <div class="flx-field"><div class="flx-label">Recibido</div><div class="flx-value">{{ $lastLog->reported_at ? $lastLog->reported_at->diffForHumans() : 'sin datos' }}</div></div>
                            <div style="margin-top:14px;"><a href="{{ route('device-logs.download', $lastLog) }}" class="flx-link-btn primary">Descargar ultimo log</a></div>
                        @else
                            <div class="flx-empty">No hay logs recibidos.</div>
                        @endif
                    </div>
                </div>
                <div class="flx-card">
                    <div class="flx-card-head"><h2 class="flx-card-title">Operativa</h2></div>
                    <div class="flx-card-body"><div class="flx-note">Pedir diagnostico o logs desde Mas acciones. Cuando el equipo suba el paquete, quedara disponible aqui.</div></div>
                </div>
            </section>

            <section x-show="tab === 'media'" x-cloak class="flx-card">
                <div class="flx-card-head"><h2 class="flx-card-title">Imagenes y videos recientes</h2></div>
                <div class="flx-list">
                    @forelse ($media as $mediaItem)
                        <div class="flx-item">
                            <div class="flx-item-top">
                                <div>
                                    <div class="flx-item-title">{{ $mediaItem->tipo }} - {{ $mediaItem->ts_start?->format('Y-m-d H:i:s') }}</div>
                                    <div class="flx-item-meta">{{ $mediaItem->size_mb ? $mediaItem->size_mb.' MB' : 'sin tamano' }}</div>
                                </div>
                                <div class="flx-actions-inline">
                                    <a href="{{ route('media.view', $mediaItem) }}" target="_blank" class="flx-link-btn">Ver</a>
                                    <a href="{{ route('media.download', $mediaItem) }}" class="flx-link-btn primary">Descargar</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flx-empty">Sin media disponible.</div>
                    @endforelse
                </div>
            </section>

            <section x-show="tab === 'telemetria'" x-cloak class="flx-card">
                <div class="flx-card-head"><h2 class="flx-card-title">Telemetria reciente</h2></div>
                <div class="flx-list">
                    @forelse ($telemetry as $t)
                        <div class="flx-item">
                            <div class="flx-item-top">
                                <div class="flx-item-title">{{ $t->ts?->format('Y-m-d H:i:s') }}</div>
                                <div class="flx-item-meta">zona {{ $t->zone ?? 'sin zona' }}</div>
                            </div>
                            <div class="flx-item-meta">
                                <span>ocupacion: {{ $t->occupancy ?? 'sin datos' }}</span>
                                <span>presion: {{ $t->pressure ?? 'sin datos' }}</span>
                                <span>congestion: {{ $t->congestion ?? 'sin datos' }}</span>
                                <span>decision: {{ $t->decision ?? 'sin datos' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="flx-empty">Sin telemetria reciente.</div>
                    @endforelse
                </div>
                <div class="flx-card-body" style="border-top:1px solid #e5e7eb;"><a href="{{ \App\Filament\Pages\Mapa::getUrl() }}" class="flx-link-btn">Ver mapa operativo</a></div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
