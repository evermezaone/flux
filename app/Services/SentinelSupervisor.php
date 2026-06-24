<?php

namespace App\Services;

use App\Models\Device;
use App\Models\StabilityEvent;

/**
 * FLX-0051: evalua en cada heartbeat las fallas industriales CRITICAS de Sentinel/permisos y, si las hay,
 * levanta un evento de estabilidad `sentinel_critical` (reusa el pipeline de FLX-0047: ingest -> status
 * critical -> alerta anti-tormenta -> supervisor de recuperacion). Lee los campos que producen VLS-0072/0074/
 * 0076 en device_metrics, en formato plano o anidado (graceful: null si el equipo aun no los reporta).
 *
 * Condiciones criticas:
 *  - Sentinel ausente o stale/muerto.
 *  - Falta POST_NOTIFICATIONS para el Sentinel.
 *  - VLS no esta al frente y el Sentinel no es recovery-capable.
 */
class SentinelSupervisor
{
    public function __construct(private StabilityIngestor $stability) {}

    /** @return array{critical: bool, reasons: array<int, string>} */
    public function evaluate(Device $device): array
    {
        $m = $device->health?->device_metrics;
        if (! is_array($m)) {
            return ['critical' => false, 'reasons' => []];
        }
        // Codex R1: aceptar AMBOS formatos (plano que envia SentinelWatch + anidado del contrato VLS-0076).
        $pick = function (array $keys) use ($m) {
            foreach ($keys as $k) {
                $v = data_get($m, $k);
                if ($v !== null) {
                    return $v;
                }
            }

            return null;
        };

        $reasons = [];

        $installed = $pick(['sentinel_installed', 'sentinel.installed']);
        $providerOk = $pick(['sentinel.provider_ok', 'sentinel_provider_ok', 'sentinel_provider_available']);
        $serviceRunning = $pick(['sentinel.service_running', 'sentinel_service_running']);
        $processSeen = $pick(['sentinel.process_seen', 'sentinel_process_seen']);
        $watch = $pick(['sentinel_watch_status', 'sentinel.sentinel_watch_status']);
        $stale = $pick(['sentinel.stale', 'sentinel_stale']);
        $logAge = $pick(['sentinel.log_age_s', 'sentinel_log_age_s']);
        $lastError = $pick(['sentinel.last_error', 'sentinel_last_error']);
        $logStaleS = (int) config('stability.sentinel_log_stale_s', 300);

        if ($installed === false) {
            $reasons[] = 'Sentinel no instalado';
        }
        if ($providerOk === false) {
            $reasons[] = 'Sentinel provider sin responder';
        }
        if ($serviceRunning === false) {
            $reasons[] = 'Sentinel sin servicio foreground';
        }
        if ($processSeen === false) {
            $reasons[] = 'Sentinel sin proceso visible';
        }
        if ($stale === true || in_array($watch, ['down', 'oem_hibernation_suspected'], true)) {
            $reasons[] = 'Sentinel muerto/stale';
        }
        if (is_numeric($logAge) && $logAge > $logStaleS) {
            $reasons[] = "Logs del Sentinel viejos ({$logAge}s)";
        }
        if (! empty($lastError) && is_string($lastError)) {
            $reasons[] = 'Sentinel error: '.mb_substr($lastError, 0, 80);
        }

        if (data_get($m, 'industrial_provisioning.post_notifications_sentinel') === false) {
            $reasons[] = 'Sentinel sin permiso de notificaciones';
        }

        $fg = $pick(['app_foreground', 'foreground.app_foreground']);
        $recoveryCapable = $pick(['sentinel.recovery_capable', 'sentinel_recovery_capable']);
        if ($fg === false && $recoveryCapable === false) {
            $reasons[] = 'VLS en background y Sentinel no puede recuperar';
        }

        if ($reasons !== []) {
            // event_id estable por hora -> idempotente (no spamea eventos cada heartbeat).
            StabilityEvent::updateOrCreate(
                ['device_id' => $device->id, 'event_id' => 'sentinel_critical-'.now()->format('Y-m-d-H')],
                [
                    'event_type' => 'sentinel_critical',
                    'severity' => 'critical',
                    'occurred_at' => now(),
                    'summary' => mb_substr(implode(' · ', $reasons), 0, 255),
                    // Codex R1: conservar causa + snapshot reducido + ultimo diagnostico conocido.
                    'details' => [
                        'reasons' => $reasons,
                        'sentinel' => [
                            'installed' => $installed, 'provider_ok' => $providerOk, 'service_running' => $serviceRunning,
                            'process_seen' => $processSeen, 'watch_status' => $watch, 'stale' => $stale,
                            'log_age_s' => $logAge, 'last_error' => $lastError,
                        ],
                        'industrial_provisioning' => data_get($m, 'industrial_provisioning'),
                    ],
                    'diagnostic_id' => $pick(['stability.last_diagnostic_id', 'sentinel.last_diagnostic_id']),
                ]
            );
            // Recalcular el estado consolidado para que dispare alerta/recuperacion (FLX-0047/0048).
            $this->stability->recompute($device, (array) ($m['stability'] ?? []));
        }

        return ['critical' => $reasons !== [], 'reasons' => $reasons];
    }
}
