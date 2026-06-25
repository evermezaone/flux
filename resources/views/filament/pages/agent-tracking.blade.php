<x-filament-panels::page>
    {{-- FLX-0054: seguimiento de agentes/REQ, mobile-first. Sin chat ni diffs: solo estado accionable. --}}
    @php
        $badge = [
            'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300',
            'info'    => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
            'danger'  => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
            'success' => 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300',
            'gray'    => 'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-300',
        ];
    @endphp

    @unless ($configured)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <p class="font-semibold">BD de coordinación no configurada</p>
            <p class="text-sm">Definí <code>COORD_DB_*</code> en el <code>.env</code> para ver el seguimiento de agentes.
            @isset($error)<br><span class="text-xs opacity-70">{{ $error }}</span>@endisset</p>
        </div>
    @else
        {{-- Contadores generales --}}
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
            @foreach ([
                ['REQ activos', $counters['activos'], 'gray'],
                ['Claude', $counters['claude'], 'primary'],
                ['Codex', $counters['codex'], 'info'],
                ['Bloqueados', $counters['bloqueados'], 'gray'],
                ['Esperando permiso', $counters['esperando_permiso'], 'warning'],
            ] as [$label, $value, $color])
                <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                    <div class="text-2xl font-bold tabular-nums {{ $color === 'warning' && $value > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ $value }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- Tarjetas accionables: esperando permiso --}}
        @if (count($permissions))
            <div class="mt-4 space-y-2">
                <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-300">Esperando tu permiso</h3>
                @foreach ($permissions as $p)
                    <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs text-amber-800 dark:text-amber-300">{{ $p['project'] }}/{{ $p['req'] }}</span>
                            <span class="text-xs text-amber-700/70 dark:text-amber-300/70">{{ $p['updated'] }}</span>
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm font-medium text-gray-900 dark:text-white">{{ $p['title'] }}</p>
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ $p['reason'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Aprobar/rechazar desde el chat del agente (no disponible desde esta vista).</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Agentes y sus REQ --}}
        <div class="mt-4 space-y-4">
            @forelse ($agents as $agent)
                <div>
                    <div class="mb-2 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white dark:bg-white dark:text-gray-900">{{ strtoupper(substr($agent['name'], 0, 1)) }}</span>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $agent['name'] }}</h3>
                        <span class="text-xs text-gray-400">{{ count($agent['reqs']) }} REQ</span>
                    </div>
                    <div class="space-y-2">
                        @foreach ($agent['reqs'] as $req)
                            <div x-data="{ open: false }" class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                                <button type="button" @click="open = !open" class="w-full text-left">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $req['project'] }}/{{ $req['req'] }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge[$req['state_color']] ?? $badge['gray'] }}">{{ $req['state_label'] }}</span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-sm font-medium text-gray-900 dark:text-white">{{ $req['title'] }}</p>
                                    <div class="mt-1 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>→ {{ $req['next'] }}</span>
                                        <span>{{ $req['updated'] }}</span>
                                    </div>
                                </button>
                                <div x-show="open" x-collapse class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-600 dark:border-white/10 dark:text-gray-300">
                                    <p><span class="text-gray-400">REQ:</span> {{ $req['project'] }}/{{ $req['req'] }}</p>
                                    <p><span class="text-gray-400">Estado:</span> {{ $req['state_label'] }}</p>
                                    @if ($req['blocked_by'])
                                        <p><span class="text-gray-400">Bloqueo:</span> {{ $req['blocked_by'] }}</p>
                                    @endif
                                    <p><span class="text-gray-400">Próxima acción:</span> {{ $req['next'] }}</p>
                                    <p><span class="text-gray-400">Actualizado:</span> {{ $req['updated'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                    No hay REQ activos. Todo cerrado. ✅
                </div>
            @endforelse
        </div>
    @endunless
</x-filament-panels::page>
