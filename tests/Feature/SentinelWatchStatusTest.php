<?php

namespace Tests\Feature;

use App\Filament\Resources\DeviceHealthResource\Tables\DeviceHealthTable;
use App\Models\DeviceHealth;
use Tests\TestCase;

/**
 * FLX-0049 R1: el panel resuelve sentinel_watch_status en AMBOS formatos (plano + anidado).
 */
class SentinelWatchStatusTest extends TestCase
{
    public function test_formato_plano_que_envia_vls(): void
    {
        $h = new DeviceHealth(['device_metrics' => ['sentinel_watch_status' => 'oem_hibernation_suspected']]);
        $this->assertSame('oem_hibernation_suspected', DeviceHealthTable::sentinelWatchStatus($h));
    }

    public function test_formato_anidado(): void
    {
        $h = new DeviceHealth(['device_metrics' => ['sentinel' => ['sentinel_watch_status' => 'ok']]]);
        $this->assertSame('ok', DeviceHealthTable::sentinelWatchStatus($h));
    }

    public function test_sin_dato(): void
    {
        $h = new DeviceHealth(['device_metrics' => []]);
        $this->assertNull(DeviceHealthTable::sentinelWatchStatus($h));
    }
}
