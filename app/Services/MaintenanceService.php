<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\GlobalSetting;
use Illuminate\Support\Carbon;

/**
 * FLX-0043: estado energetico + mantenimiento preventivo. Evalua la bateria/temperatura del ultimo
 * heartbeat contra umbrales (configurables por equipo), y calcula edad operativa + recomendacion de
 * revision/reemplazo desde la fecha de instalacion. No inventa medicion solar: la fuente es un campo
 * manual del equipo (power_source).
 */
class MaintenanceService
{
    /** @return array{level:string,battery_pct:?int,temp_c:mixed,temp_alert:bool,alert:bool} */
    public function energyState(Device $device): array
    {
        $metrics = $device->health?->device_metrics ?? [];
        $battery = isset($metrics['battery_pct']) ? (int) $metrics['battery_pct'] : null;
        $temp = $metrics['temp_c'] ?? null;

        $warn = (int) $this->setting($device, 'battery_warning_pct', config('energy.battery_warning_pct', 50));
        $crit = (int) $this->setting($device, 'battery_critical_pct', config('energy.battery_critical_pct', 30));
        $shut = (int) $this->setting($device, 'battery_shutdown_pct', config('energy.battery_shutdown_pct', 15));
        $tempMax = (int) $this->setting($device, 'temp_max_c', config('energy.temp_max_c', 0));

        $level = 'unknown';
        if ($battery !== null) {
            $level = match (true) {
                $battery <= $shut => 'shutdown',
                $battery <= $crit => 'critical',
                $battery <= $warn => 'warning',
                default => 'ok',
            };
        }
        $tempAlert = $tempMax > 0 && $temp !== null && (float) $temp >= $tempMax;

        return [
            'level' => $level,
            'battery_pct' => $battery,
            'temp_c' => $temp,
            'temp_alert' => $tempAlert,
            // FLX-0043 (Codex): el usuario pidio alerta DESDE 50% (warning). alert cubre warning|critical|shutdown.
            'alert' => in_array($level, ['warning', 'critical', 'shutdown'], true) || $tempAlert,
        ];
    }

    public function ageMonths(Device $device): ?int
    {
        if (! $device->install_date) {
            return null;
        }

        return (int) Carbon::parse($device->install_date)->diffInMonths(Carbon::now(), absolute: true);
    }

    /** @return array{status:string,age_months:?int,text:string} */
    public function recommendation(Device $device): array
    {
        $age = $this->ageMonths($device);
        if ($age === null) {
            return ['status' => 'sin_fecha', 'age_months' => null, 'text' => 'sin fecha de instalación'];
        }

        $review = (int) $this->setting($device, 'review_months', config('energy.review_months', 12));
        $replace = (int) $this->setting($device, 'replace_months', config('energy.replace_months', 36));

        $status = match (true) {
            $age >= $replace => 'reemplazo',
            $age >= $review => 'revision',
            default => 'ok',
        };
        $text = match ($status) {
            'reemplazo' => "reemplazo sugerido (>= {$replace} meses)",
            'revision' => "revisión sugerida (>= {$review} meses)",
            default => 'ok',
        };

        return ['status' => $status, 'age_months' => $age, 'text' => $text];
    }

    private function setting(Device $device, string $key, $default)
    {
        $v = DeviceSetting::where('device_id', $device->id)->where('key', $key)->value('value')
            ?? GlobalSetting::where('key', $key)->value('value');

        return $v ?? $default;
    }
}
