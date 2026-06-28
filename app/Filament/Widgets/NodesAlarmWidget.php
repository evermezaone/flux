<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Devices\DeviceResource;
use App\Models\Device;
use Filament\Widgets\Widget;

/**
 * FLX-0060: ALARMA clara y visible en el dashboard de los nodos que NO funcionan bien. Aparece arriba de todo
 * (sort negativo) y se actualiza solo (poll 15s). Reusa DeviceHealth::effectiveStatus() y el bloque
 * device_metrics del heartbeat (incluye detection.no_reading = camara sin lectura, VLS-0066/0095).
 *
 * Un nodo entra en alarma si: sin latido (offline), salud fail, camara sin lectura, requiere intervencion,
 * o inestable (crash/ANR critico). Si no hay ninguno, muestra un estado verde "todo OK".
 */
class NodesAlarmWidget extends Widget
{
    protected string $view = 'filament.widgets.nodes-alarm';

    // FLX-0060: arriba de todo (FlxStatsWidget es sort=1).
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    // Se refresca solo (sin recargar la pagina).
    protected ?string $pollingInterval = '15s';

    protected function getViewData(): array
    {
        $offlineMin = (int) config('health.offline_minutes', 5);

        $nodes = Device::with(['health', 'stabilityState', 'site'])
            ->get()
            ->map(function (Device $d) use ($offlineMin): array {
                $h = $d->health;
                $m = is_array($h?->device_metrics) ? $h->device_metrics : [];
                $status = $h?->effectiveStatus($offlineMin) ?? 'offline';

                $reasons = [];
                $severity = 0; // mayor = mas grave (para ordenar)

                if ($status === 'offline') {
                    $reasons[] = 'Sin latido (offline)';
                    $severity = max($severity, 5);
                } elseif ($status === 'fail') {
                    $reasons[] = 'Salud crítica (fail)';
                    $severity = max($severity, 4);
                } elseif ($status === 'warn') {
                    $reasons[] = 'Salud degradada (warn)';
                    $severity = max($severity, 1);
                }

                if (data_get($m, 'detection.no_reading') === true) {
                    $reasons[] = 'Cámara sin lectura';
                    $severity = max($severity, 4);
                }
                if (data_get($m, 'requires_intervention') === true) {
                    $reasons[] = 'Requiere intervención';
                    $severity = max($severity, 3);
                }
                if (optional($d->stabilityState)->stability_status === 'critical') {
                    $reasons[] = 'Inestable (crash/ANR)';
                    $severity = max($severity, 3);
                }

                return [
                    'id' => $d->id,
                    'code' => $d->code,
                    'site' => $d->site?->code ?? $d->site?->name ?? '—',
                    'reasons' => $reasons,
                    'severity' => $severity,
                    'status' => $status,
                    'last_seen' => $h?->reported_at?->diffForHumans() ?? 'nunca',
                    'url' => DeviceResource::getUrl('view', ['record' => $d->id]),
                ];
            })
            ->filter(fn (array $n): bool => ! empty($n['reasons']))
            ->sortByDesc('severity')
            ->values()
            ->all();

        return ['nodes' => $nodes, 'count' => count($nodes)];
    }
}
