<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Notifications\StabilityAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * FLX-0047: alertas de estabilidad por email con anti-tormenta + escalado + recuperacion.
 *
 * Anti-tormenta: se guarda en `device_stability_states.alerted_status` el ultimo nivel avisado.
 *  - ok -> warn/critical: alerta (entrada).
 *  - warn -> critical: re-alerta (ESCALADO).
 *  - critical -> warn: solo actualiza el nivel (sin spam de "bajada").
 *  - warn/critical -> ok: aviso de RECUPERACION.
 *  - mismo nivel repetido: NO re-alerta.
 *
 * Destinatarios en config('stability.alert_email') (reusa health.alert_email por defecto).
 */
class CheckStabilityAlerts extends Command
{
    protected $signature = 'stability:check-alerts';

    protected $description = 'Avisa por email cuando un equipo entra/escala en inestabilidad o se recupera.';

    private const ORDER = ['ok' => 0, 'warn' => 1, 'critical' => 2];

    public function handle(): int
    {
        $recipients = array_filter(array_map('trim', explode(',', (string) config('stability.alert_email', ''))));
        $sent = 0;

        foreach (Device::with('stabilityState')->get() as $device) {
            $st = $device->stabilityState;
            if (! $st) {
                continue;
            }
            $cur = $st->stability_status; // ok | warn | critical
            $prev = $st->alerted_status;  // null si no estaba en alerta
            $curLevel = self::ORDER[$cur] ?? 0;
            $prevLevel = self::ORDER[$prev] ?? 0;

            if ($cur !== 'ok' && $curLevel > $prevLevel) {
                // Entrada o escalado (ok->warn, warn->critical): alertar.
                $this->notify($recipients, $device, $cur, recovered: false);
                $st->forceFill(['alerted_status' => $cur])->save();
                $sent++;
                $this->warn(($prev ? 'ESCALA' : 'ALERTA')." {$device->code}: {$prev} -> {$cur}");
            } elseif ($cur === 'ok' && $prev !== null) {
                // Recuperacion.
                $this->notify($recipients, $device, $cur, recovered: true);
                $st->forceFill(['alerted_status' => null])->save();
                $sent++;
                $this->info("RECUPERADO {$device->code}");
            } elseif ($cur !== 'ok' && $cur !== $prev) {
                // Bajada de severidad (critical->warn): actualizar el nivel sin spam.
                $st->forceFill(['alerted_status' => $cur])->save();
            }
        }

        $this->info("stability:check-alerts -> {$sent} aviso(s).");

        return self::SUCCESS;
    }

    /** @param array<int, string> $recipients */
    private function notify(array $recipients, Device $device, string $status, bool $recovered): void
    {
        if (empty($recipients)) {
            return; // sin destinatarios: igual se marca el estado (anti-tormenta), pero no se envia
        }
        $detail = $recovered ? '' : sprintf(
            'crash:%d anr:%d ui:%d app_error:%d (24h)',
            $device->stabilityState->crash_count_24h,
            $device->stabilityState->anr_count_24h,
            $device->stabilityState->ui_freeze_count_24h,
            $device->stabilityState->app_error_count_24h,
        );
        Notification::route('mail', $recipients)->notify(new StabilityAlert($device, $status, $recovered, $detail));
    }
}
