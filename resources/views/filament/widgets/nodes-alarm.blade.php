<x-filament-widgets::widget>
    <x-filament::section>
        @if ($count === 0)
            <div class="flex items-center gap-3 rounded-lg bg-success-50 p-4 dark:bg-success-500/10">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-7 w-7 text-success-600" />
                <span class="text-base font-semibold text-success-700 dark:text-success-400">
                    Todos los nodos funcionando correctamente.
                </span>
            </div>
        @else
            <div class="space-y-3">
                <div class="flex items-center gap-3 rounded-lg bg-danger-600 p-4 text-white shadow">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-7 w-7" />
                    <span class="text-lg font-bold">
                        {{ $count }} {{ $count === 1 ? 'nodo' : 'nodos' }} en alarma
                    </span>
                </div>

                <div class="overflow-hidden rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 font-medium">Nodo</th>
                                <th class="px-3 py-2 font-medium">Cruce</th>
                                <th class="px-3 py-2 font-medium">Problema</th>
                                <th class="px-3 py-2 font-medium">Último latido</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($nodes as $n)
                                <tr class="cursor-pointer hover:bg-danger-50 dark:hover:bg-danger-500/10"
                                    onclick="window.location.href='{{ $n['url'] }}'">
                                    <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white">{{ $n['code'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $n['site'] }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($n['reasons'] as $r)
                                                <span class="inline-flex rounded-full bg-danger-100 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/20 dark:text-danger-300">
                                                    {{ $r }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500">{{ $n['last_seen'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
