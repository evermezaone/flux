<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceRequirementState;

/**
 * FLX-0044: convierte el bloque `device_metrics.operational_requirements` (que produce VLS-0063) en estado
 * persistido + alertas. Anti-tormenta: solo registra CAMBIOS de conjunto de fallos y la recuperacion (no
 * re-alerta en cada heartbeat). Mantiene `failing_since` (duracion) y contadores critical/warning.
 *
 * Contrato esperado (robusto a faltantes):
 *   operational_requirements.checks = { <check>: { ok: bool, severity: "critical|warning", detail: string } }
 */
class RequirementsMonitor
{
    /** Evalua el ultimo heartbeat del equipo y persiste el estado de prerequisitos. null si no lo reporta. */
    public function evaluate(Device $device): ?DeviceRequirementState
    {
        $metrics = $device->health?->device_metrics ?? [];
        $req = $metrics['operational_requirements'] ?? null;
        if (! is_array($req)) {
            return null; // el equipo aun no reporta el bloque (VLS-0063 no desplegado)
        }

        $checks = is_array($req['checks'] ?? null) ? $req['checks'] : [];
        $failures = [];
        $critical = 0;
        $warning = 0;
        foreach ($checks as $name => $c) {
            if (! is_array($c) || (bool) ($c['ok'] ?? true)) {
                continue;
            }
            $sev = ($c['severity'] ?? 'warning') === 'critical' ? 'critical' : 'warning';
            $failures[] = ['check' => $name, 'severity' => $sev, 'detail' => (string) ($c['detail'] ?? '')];
            $sev === 'critical' ? $critical++ : $warning++;
        }
        $ok = $critical === 0;

        $state = DeviceRequirementState::firstOrNew(['device_id' => $device->id]);
        $wasFailing = $state->exists && ! $state->ok;
        $prevKey = self::failuresKey($state->failures ?? []);
        $newKey = self::failuresKey($failures);
        $now = now();

        // Anti-tormenta: registrar el cambio solo si el conjunto de fallos cambio.
        if (! $state->exists || $prevKey !== $newKey) {
            $state->last_changed_at = $now;
        }
        // Duracion del fallo: arranca al empezar a fallar; se limpia al recuperar.
        if (! empty($failures)) {
            if (empty($state->failing_since) || ($state->exists && $state->ok)) {
                $state->failing_since = $now;
            }
        } else {
            $state->failing_since = null;
        }
        // Recuperacion: venia fallando criticamente y ahora esta OK.
        if ($wasFailing && $ok) {
            $state->last_recovery_at = $now;
        }

        $state->ok = $ok;
        $state->critical_count = $critical;
        $state->warning_count = $warning;
        $state->failures = $failures;
        $state->save();

        return $state;
    }

    /** @param array<int,array<string,mixed>> $failures */
    private static function failuresKey(array $failures): string
    {
        $keys = array_map(static fn ($f) => ($f['check'] ?? '').':'.($f['severity'] ?? ''), $failures);
        sort($keys);

        return implode('|', $keys);
    }
}
