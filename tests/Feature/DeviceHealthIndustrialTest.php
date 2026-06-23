<?php

namespace Tests\Feature;

use App\Filament\Resources\DeviceHealthResource\Tables\DeviceHealthTable;
use App\Models\Device;
use App\Models\DeviceHealth;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLX-0042: el indicador industrial maneja `industrial_profile` como BLOQUE (VLS-0058) sin romper, y
 * mantiene compatibilidad con bool/string.
 */
class DeviceHealthIndustrialTest extends TestCase
{
    use RefreshDatabase;

    private function health(array $metrics): DeviceHealth
    {
        $site = Site::firstOrCreate(['code' => 'ruta2_cruce1']);
        $d = Device::create(['site_id' => $site->id, 'code' => 'tel-ind', 'device_key' => 'k-ind', 'active' => true]);
        $h = new DeviceHealth;
        $h->forceFill(['device_id' => $d->id, 'overall' => 'ok', 'device_metrics' => $metrics, 'reported_at' => now()])->save();

        return $h->fresh();
    }

    public function test_industrial_profile_como_bloque(): void
    {
        $h = $this->health(['industrial_profile' => [
            'device_owner' => true,
            'keyguard_disabled' => true,
            'lock_task_active' => true,
            'unknown_sources_restricted' => false,
            'profile_last_error' => 'no se pudo fijar HOME',
        ]]);

        $s = DeviceHealthTable::industrial($h);

        $this->assertStringContainsString('perfil:', $s);
        $this->assertStringContainsString('kiosk activo: sí', $s);
        $this->assertStringContainsString('orígenes desc. restringidos: no', $s);
        $this->assertStringContainsString('error: no se pudo fijar HOME', $s);
        $this->assertStringNotContainsString('Array', $s); // no rompe con el objeto
    }

    public function test_industrial_profile_compat_bool(): void
    {
        $h = $this->health(['industrial_profile' => true]);
        $this->assertStringContainsString('perfil: sí', DeviceHealthTable::industrial($h));
    }

    public function test_sin_industrial_devuelve_guion(): void
    {
        $h = $this->health([]);
        $this->assertSame('—', DeviceHealthTable::industrial($h));
    }
}
