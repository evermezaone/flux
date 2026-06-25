<x-filament-panels::page>
    @php
        $hm = $health?->device_metrics ?? [];
        $subsystems = $health?->subsystems ?? [];
        $site = $device->site;
        $status = $health?->overall ?? 'sin datos';
        $reportedAt = $health?->reported_at;
        $lastSeen = $device->last_seen_at;
        $fg = data_get($hm, 'app_foreground');
        $battery = data_get($hm, 'battery_pct');
        $vlsVer = data_get($hm, 'apps.vls.version_name') ?? ($health?->app_version ?? null);
        $vlsCode = data_get($hm, 'apps.vls.version_code');
        $senVer = data_get($hm, 'apps.sentinel.version_name');
        $senCode = data_get($hm, 'apps.sentinel.version_code');
        $maskKey = $device->device_key ? (substr($device->device_key, 0, 3).'***'.substr($device->device_key, -2)) : null;

        $statusTone = match ($status) {
            'ok' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/30',
            'warn' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
            'fail' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
        };

        $fgLabel = $fg === null ? 'desconocido' : ($fg ? 'al frente' : 'background');
        $fgTone = $fg === true
            ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300'
            : ($fg === false
                ? 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300'
                : 'bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300');

        $reqOk = $requirements?->ok;
        $reqTone = $reqOk === true
            ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300'
            : ($requirements
                ? 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300'
                : 'bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300');

        $stabilityStatus = $stability?->stability_status ?? 'sin datos';
        $stabilityTone = match ($stabilityStatus) {
            'ok' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300',
            'warn' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300',
            'critical' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300',
            default => 'bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300',
        };

        $pill = fn ($text, $tone) => '<span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset '.$tone.'">'.e($text).'</span>';
        $metric = function ($label, $value, $hint = null) {
            return '<div class="rounded-lg border border-zinc-800 bg-white/5 px-4 py-3">'.
                '<div class="text-xs font-medium text-zinc-400">'.e($label).'</div>'.
                '<div class="mt-1 truncate text-base font-semibold text-white">'.$value.'</div>'.
                ($hint ? '<div class="mt-1 truncate text-xs text-zinc-400">'.e($hint).'</div>' : '').
            '</div>';
        };
        $field = function ($label, $value) {
            return '<div class="grid gap-1 border-b border-gray-100 py-2 last:border-0 dark:border-white/5 sm:grid-cols-3 sm:gap-4">'.
                '<dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">'.e($label).'</dt>'.
                '<dd class="text-sm text-gray-950 dark:text-white sm:col-span-2">'.$value.'</dd>'.
            '</div>';
        };
        $fmt = function ($value) {
            if ($value === null || $value === '') {
                return 'sin datos';
            }

            if (is_bool($value)) {
                return $value ? 'si' : 'no';
            }

            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return (string) $value;
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

    <div x-data="{ tab: 'resumen' }" class="space-y-5">
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="bg-zinc-950 px-5 py-5 text-white">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            {!! $pill(strtoupper($status), $statusTone) !!}
                            {!! $pill('VLS '.$fgLabel, $fgTone) !!}
                            {!! $pill($device->active ? 'activo' : 'inactivo', $device->active ? 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300') !!}
                        </div>
                        <h1 class="mt-3 break-words text-3xl font-semibold tracking-normal">
                            {{ $device->code }}
                        </h1>
                        <p class="mt-1 text-sm text-zinc-300">
                            {{ $site?->code ?? 'sin sitio' }}@if($device->model) - {{ $device->model }}@endif
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4 lg:min-w-[34rem]">
                        {!! $metric('Ultimo latido', $reportedAt ? e($reportedAt->diffForHumans()) : '<span class="text-gray-400">sin datos</span>', $reportedAt?->format('Y-m-d H:i:s')) !!}
                        {!! $metric('Bateria', $battery !== null ? e($battery).'%' : '<span class="text-gray-400">sin datos</span>') !!}
                        {!! $metric('VLS', $vlsVer ? e($vlsVer) : '<span class="text-gray-400">sin version</span>', $vlsCode ? 'code '.$vlsCode : null) !!}
                        {!! $metric('Sentinel', $senVer ? e($senVer) : '<span class="text-gray-400">sin version</span>', $senCode ? 'code '.$senCode : null) !!}
                    </div>
                </div>
            </div>

            <div class="grid divide-y divide-gray-100 border-b border-gray-100 dark:divide-white/10 dark:border-white/10 md:grid-cols-3 md:divide-x md:divide-y-0">
                <div class="p-5">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Supervision remota</div>
                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $supervision?->state ?? 'sin estado' }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $supervision?->reason ?? 'sin motivo activo' }}
                    </div>
                </div>
                <div class="p-5">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Prerequisitos</div>
                    <div class="mt-1">{!! $pill($reqOk === true ? 'OK' : ($requirements ? 'requiere atencion' : 'sin datos'), $reqTone) !!}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Criticos: {{ $requirements?->critical_count ?? 0 }} - Warnings: {{ $requirements?->warning_count ?? 0 }}
                    </div>
                </div>
                <div class="p-5">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Estabilidad</div>
                    <div class="mt-1">{!! $pill($stabilityStatus, $stabilityTone) !!}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Crash {{ $stability?->crash_count_24h ?? 0 }} - ANR {{ $stability?->anr_count_24h ?? 0 }} - UI {{ $stability?->ui_freeze_count_24h ?? 0 }}
                    </div>
                </div>
            </div>
        </section>

        <nav class="flex gap-1 overflow-x-auto rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-white/10 dark:bg-white/5" aria-label="Device tabs">
            @foreach ($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'bg-white text-gray-950 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-white dark:ring-white/10'
                        : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition">
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        <section x-show="tab === 'resumen'" class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Identidad y conectividad</h2>
                <dl class="mt-4">
                    {!! $field('Codigo', e($device->code)) !!}
                    {!! $field('Sitio', e($site?->code ?? 'sin sitio')) !!}
                    {!! $field('Modelo', e($device->model ?? 'sin modelo')) !!}
                    {!! $field('Device key', $maskKey ? '<span class="font-mono">'.e($maskKey).'</span>' : '<span class="text-gray-400">sin key</span>') !!}
                    {!! $field('FCM token', $device->fcm_token ? '<span class="text-green-600 dark:text-green-400">presente</span>' : '<span class="text-amber-600 dark:text-amber-400">ausente</span>') !!}
                    {!! $field('FCM actualizado', $device->fcm_token_at ? e($device->fcm_token_at->diffForHumans()) : '<span class="text-gray-400">sin datos</span>') !!}
                    {!! $field('Ultima comunicacion', $lastSeen ? e($lastSeen->diffForHumans()).' <span class="text-xs text-gray-500">('.e($lastSeen->format('Y-m-d H:i:s')).')</span>' : '<span class="text-gray-400">sin datos</span>') !!}
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Acciones disponibles</h2>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    Los comandos operativos estan arriba en la barra de acciones. Usar FCM para respuesta inmediata y polling cuando el equipo solo consulta cola.
                </p>
                <div class="mt-4 space-y-2 text-xs text-gray-500 dark:text-gray-400">
                    <div>Reinicio app/equipo, despertar push, detener/reanudar.</div>
                    <div>Diagnostico, logs, estabilidad y mantenimiento.</div>
                    <div>Historial completo en la pestana Comandos.</div>
                </div>
            </div>
        </section>

        <section x-show="tab === 'salud'" class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Estado operativo</h2>
                <dl class="mt-4">
                    {!! $field('Estado global', '<span class="uppercase">'.e($status).'</span>') !!}
                    {!! $field('App al frente', e($fgLabel)) !!}
                    {!! $field('Red', e($fmt(data_get($hm, 'network.type', data_get($hm, 'network'))))) !!}
                    {!! $field('Sentinel', e($fmt(data_get($hm, 'sentinel.sentinel_watch_status', data_get($hm, 'sentinel_watch_status', data_get($hm, 'sentinel')))))) !!}
                    {!! $field('Device Owner', e($fmt(data_get($hm, 'device_owner.enabled', data_get($hm, 'device_owner'))))) !!}
                    {!! $field('Lock task', e($fmt(data_get($hm, 'lock_task.active', data_get($hm, 'lock_task'))))) !!}
                    {!! $field('Intervencion', !empty($hm['requires_intervention']) ? '<span class="text-red-600 dark:text-red-400">requerida</span>' : 'no requerida') !!}
                    {!! $field('Uptime', $health?->uptime_s ? number_format($health->uptime_s).' s' : '<span class="text-gray-400">sin datos</span>') !!}
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Fallas y estabilidad</h2>
                <div class="mt-4 space-y-3">
                    @if ($requirements?->failures)
                        @foreach ($requirements->failures as $failure)
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
                                <div class="font-medium">{{ data_get($failure, 'code', data_get($failure, 'name', 'Falla')) }}</div>
                                <div class="mt-1 text-xs opacity-80">{{ data_get($failure, 'message', json_encode($failure)) }}</div>
                            </div>
                        @endforeach
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                            No hay fallas de prerequisitos registradas.
                        </div>
                    @endif

                    <dl>
                        {!! $field('Ultimo evento', e($stability?->last_stability_event ?? 'sin datos')) !!}
                        {!! $field('Ultimo evento en', $stability?->last_stability_event_at ? e($stability->last_stability_event_at->diffForHumans()) : '<span class="text-gray-400">sin datos</span>') !!}
                        {!! $field('UI congelada', $stability?->ui_frozen === null ? '<span class="text-gray-400">sin datos</span>' : ($stability->ui_frozen ? '<span class="text-red-600 dark:text-red-400">si</span>' : 'no')) !!}
                        {!! $field('Ultimo tick UI', $stability?->ui_last_tick_at ? e($stability->ui_last_tick_at->diffForHumans()) : '<span class="text-gray-400">sin datos</span>') !!}
                        {!! $field('Recuperacion', e($stability?->last_recovery_action ?? 'sin accion')) !!}
                    </dl>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Eventos de estabilidad recientes</h2>
                <div class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($stabilityEvents as $event)
                        <div class="py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $event->event_type }} - {{ $event->severity }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $event->occurred_at?->format('Y-m-d H:i:s') }}</div>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $event->summary ?: 'Sin resumen' }}</p>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-gray-500 dark:text-gray-400">Sin eventos de estabilidad recientes.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section x-show="tab === 'comandos'" class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Historial de comandos</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($commands as $c)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ $c->cmd }}</div>
                            <span @class([
                                'rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-300' => $c->status === 'done',
                                'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300' => $c->status === 'failed',
                                'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300' => $c->status === 'pending',
                                'bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300' => ! in_array($c->status, ['done', 'failed', 'pending']),
                            ])>{{ $c->status }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span>canal: {{ $c->channel }}@if ($c->exec_channel && $c->exec_channel !== $c->channel) -> {{ $c->exec_channel }}@endif</span>
                            <span>creado: {{ $c->created_at?->diffForHumans() }}</span>
                            @if ($c->picked_at)<span>tomado: {{ $c->picked_at->diffForHumans() }}</span>@endif
                            @if ($c->done_at)<span>cerrado: {{ $c->done_at->diffForHumans() }}</span>@endif
                        </div>
                        @if ($c->result)
                            <p class="mt-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($c->result, 260) }}</p>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin comandos registrados.</p>
                @endforelse
            </div>
        </section>

        <section x-show="tab === 'logs'" class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Ultimo paquete de logs</h2>
                @if ($lastLog)
                    <dl class="mt-4">
                        {!! $field('Origen', e($lastLog->source)) !!}
                        {!! $field('Resumen', e($lastLog->summary ?: 'sin resumen')) !!}
                        {!! $field('Build', e($lastLog->build ?: 'sin build')) !!}
                        {!! $field('Tamano', $lastLog->size ? number_format($lastLog->size / 1024, 0).' KB' : '<span class="text-gray-400">sin datos</span>') !!}
                        {!! $field('Recibido', $lastLog->reported_at ? e($lastLog->reported_at->diffForHumans()) : '<span class="text-gray-400">sin datos</span>') !!}
                    </dl>
                    <a href="{{ route('device-logs.download', $lastLog) }}" class="mt-4 inline-flex rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        Descargar ultimo log
                    </a>
                @else
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No hay logs recibidos.</p>
                @endif
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Operativa</h2>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    Para analizar un equipo en campo, primero pedir diagnostico o logs desde la barra superior; cuando el equipo suba el paquete, quedara disponible aqui.
                </p>
            </div>
        </section>

        <section x-show="tab === 'media'" class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Imagenes y videos recientes</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($media as $mediaItem)
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $mediaItem->tipo }} - {{ $mediaItem->ts_start?->format('Y-m-d H:i:s') }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $mediaItem->size_mb ? $mediaItem->size_mb.' MB' : 'sin tamano' }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2 text-sm">
                            <a href="{{ route('media.view', $mediaItem) }}" target="_blank" class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">Ver</a>
                            <a href="{{ route('media.download', $mediaItem) }}" class="rounded-lg bg-primary-600 px-3 py-1.5 text-white hover:bg-primary-500">Descargar</a>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin media disponible.</p>
                @endforelse
            </div>
        </section>

        <section x-show="tab === 'telemetria'" class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Telemetria reciente</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($telemetry as $t)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $t->ts?->format('Y-m-d H:i:s') }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">zona {{ $t->zone ?? 'sin zona' }}</div>
                        </div>
                        <div class="mt-2 grid gap-2 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-4">
                            <span>ocupacion: {{ $t->occupancy ?? 'sin datos' }}</span>
                            <span>presion: {{ $t->pressure ?? 'sin datos' }}</span>
                            <span>congestion: {{ $t->congestion ?? 'sin datos' }}</span>
                            <span>decision: {{ $t->decision ?? 'sin datos' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin telemetria reciente.</p>
                @endforelse
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-white/10">
                <a href="{{ \App\Filament\Pages\Mapa::getUrl() }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Ver mapa operativo</a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
