<x-filament-panels::page>
    {{-- FLX-0057: ficha central del dispositivo. Pestanas compactas, mobile-first, sin scroll horizontal. --}}
    @php
        $m = $device->device_metrics ?? ($health?->device_metrics ?? []);
        $hm = $health?->device_metrics ?? [];
        $maskKey = $device->device_key ? (substr($device->device_key, 0, 3).'•••'.substr($device->device_key, -2)) : '—';
        $vlsVer = data_get($hm, 'apps.vls.version_name') ?? ($health?->app_version ?? '—');
        $senVer = data_get($hm, 'apps.sentinel.version_name') ?? '—';
        $tabs = ['resumen' => 'Resumen', 'salud' => 'Salud', 'historial' => 'Historial', 'logs' => 'Logs', 'media' => 'Media', 'telemetria' => 'Telemetría'];
        $row = fn ($k, $v) => '<div class="flex justify-between gap-3 py-1 border-b border-gray-100 dark:border-white/5"><span class="text-gray-500 dark:text-gray-400">'.$k.'</span><span class="font-medium text-gray-900 dark:text-white text-right">'.$v.'</span></div>';
    @endphp

    <div x-data="{ tab: 'resumen' }">
        {{-- Tabs --}}
        <div class="flex gap-1 overflow-x-auto border-b border-gray-200 pb-px dark:border-white/10">
            @foreach ($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                    class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium">{{ $label }}</button>
            @endforeach
        </div>

        <div class="mt-4 space-y-2 text-sm">
            {{-- RESUMEN --}}
            <div x-show="tab === 'resumen'" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                {!! $row('Código', e($device->code)) !!}
                {!! $row('Sitio / cruce', e($device->site?->code ?? '—')) !!}
                {!! $row('Modelo', e($device->model ?? '—')) !!}
                {!! $row('Activo', $device->active ? 'Sí' : 'No') !!}
                {!! $row('Device key', '<span class="font-mono">'.e($maskKey).'</span>') !!}
                {!! $row('FCM token', $device->fcm_token ? '<span class="text-green-600 dark:text-green-400">presente</span>' : '<span class="text-amber-600 dark:text-amber-400">ausente</span>') !!}
                {!! $row('Última comunicación', $device->last_seen_at ? e($device->last_seen_at->diffForHumans()) : '—') !!}
                {!! $row('Versión VLS', e($vlsVer)) !!}
                {!! $row('Versión Sentinel', e($senVer)) !!}
            </div>

            {{-- SALUD --}}
            <div x-show="tab === 'salud'" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                {!! $row('Estado global', '<span class="uppercase">'.e($health?->overall ?? 'sin datos').'</span>') !!}
                {!! $row('Batería', isset($hm['battery_pct']) ? e($hm['battery_pct']).'%' : '—') !!}
                {!! $row('Red', e(data_get($hm, 'network.type', '—'))) !!}
                {!! $row('App al frente', array_key_exists('app_foreground', $hm) ? ($hm['app_foreground'] ? 'sí' : 'no') : '—') !!}
                {!! $row('Sentinel', e(data_get($hm, 'sentinel.sentinel_watch_status', data_get($hm, 'sentinel_watch_status', '—')))) !!}
                {!! $row('Requiere intervención', !empty($hm['requires_intervention']) ? 'sí' : 'no') !!}
                {!! $row('Último reporte', $health?->reported_at ? e($health->reported_at->diffForHumans()) : '—') !!}
                <p class="mt-2 text-xs text-gray-400">Para más detalle usá los comandos del header (diagnóstico, logs).</p>
            </div>

            {{-- HISTORIAL DE COMANDOS --}}
            <div x-show="tab === 'historial'" class="rounded-xl border border-gray-200 bg-white p-2 dark:border-white/10 dark:bg-white/5">
                @forelse ($commands as $c)
                    <div class="border-b border-gray-100 px-2 py-2 last:border-0 dark:border-white/5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ $c->cmd }}</span>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs',
                                'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300' => $c->status === 'done',
                                'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300' => $c->status === 'failed',
                                'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-300' => ! in_array($c->status, ['done', 'failed']),
                            ])>{{ $c->status }}</span>
                        </div>
                        <div class="mt-0.5 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $c->channel }}@if ($c->exec_channel && $c->exec_channel !== $c->channel) → {{ $c->exec_channel }}@endif</span>
                            <span>{{ $c->created_at?->diffForHumans() }}</span>
                        </div>
                        @if ($c->result)
                            <p class="mt-0.5 line-clamp-2 text-xs text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($c->result, 160) }}</p>
                        @endif
                    </div>
                @empty
                    <p class="p-4 text-center text-gray-400">Sin comandos.</p>
                @endforelse
            </div>

            {{-- LOGS --}}
            <div x-show="tab === 'logs'" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                @if ($lastLog)
                    {!! $row('Origen', e($lastLog->source)) !!}
                    {!! $row('Resumen', e($lastLog->summary ?: '—')) !!}
                    {!! $row('Tamaño', $lastLog->size ? number_format($lastLog->size / 1024, 0).' KB' : '—') !!}
                    {!! $row('Recibido', $lastLog->reported_at ? e($lastLog->reported_at->diffForHumans()) : '—') !!}
                    <a href="{{ route('device-logs.download', $lastLog) }}" class="mt-3 inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-500">Descargar último log</a>
                @else
                    <p class="text-gray-400">No hay logs recibidos.</p>
                @endif
                <p class="mt-2 text-xs text-gray-400">Pedí un paquete nuevo con “Solicitar logs” en el header.</p>
            </div>

            {{-- MEDIA --}}
            <div x-show="tab === 'media'" class="rounded-xl border border-gray-200 bg-white p-2 dark:border-white/10 dark:bg-white/5">
                @forelse ($media as $mediaItem)
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-2 py-2 last:border-0 dark:border-white/5">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-medium text-gray-900 dark:text-white">{{ $mediaItem->tipo }} · {{ $mediaItem->ts_start?->format('d/m H:i') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $mediaItem->size_mb ? $mediaItem->size_mb.' MB' : '' }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2 text-xs">
                            <a href="{{ route('media.view', $mediaItem) }}" target="_blank" class="text-primary-600 hover:underline dark:text-primary-400">Ver</a>
                            <a href="{{ route('media.download', $mediaItem) }}" class="text-gray-500 hover:underline">Descargar</a>
                        </div>
                    </div>
                @empty
                    <p class="p-4 text-center text-gray-400">Sin media disponible.</p>
                @endforelse
            </div>

            {{-- TELEMETRIA --}}
            <div x-show="tab === 'telemetria'" class="rounded-xl border border-gray-200 bg-white p-2 dark:border-white/10 dark:bg-white/5">
                @forelse ($telemetry as $t)
                    <div class="border-b border-gray-100 px-2 py-2 last:border-0 dark:border-white/5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $t->ts?->format('d/m H:i:s') }}</span>
                            <span class="text-gray-500 dark:text-gray-400">zona {{ $t->zone ?? '—' }}</span>
                        </div>
                        <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-600 dark:text-gray-300">
                            <span>ocupación: {{ $t->occupancy ?? '—' }}</span>
                            <span>presión: {{ $t->pressure ?? '—' }}</span>
                            <span>congestión: {{ $t->congestion ?? '—' }}</span>
                            <span>decisión: {{ $t->decision ?? '—' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="p-4 text-center text-gray-400">Sin telemetría reciente.</p>
                @endforelse
                <div class="p-2">
                    <a href="{{ \App\Filament\Pages\Mapa::getUrl() }}" class="text-xs text-primary-600 hover:underline dark:text-primary-400">Ver mapa / telemetría completa →</a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
