<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * GET /api/v1/app/latest: manifiesto de version de la app VialSense (FLX REQ-0014).
 * Publico (sin X-Device-Key) y leido de config (config:cache-safe).
 */
class AppVersionTest extends TestCase
{
    public function test_manifiesto_devuelve_estructura_y_es_publico(): void
    {
        config()->set('app_release', [
            'version_code' => 15,
            'version_name' => '1.5',
            'apk_url' => 'https://one.com.py/vls/app-debug.apk',
            'notes' => 'Notas de prueba.',
        ]);

        $res = $this->getJson('/api/v1/app/latest');

        $res->assertOk()
            ->assertExactJson([
                'version_code' => 15,
                'version_name' => '1.5',
                'apk_url' => 'https://one.com.py/vls/app-debug.apk',
                'notes' => 'Notas de prueba.',
            ]);
    }

    public function test_apk_url_por_defecto_apunta_al_apk_publicado(): void
    {
        // Default real verificado (HTTP 200): el APK vive en one.com.py/vls, no en /flux.
        $this->assertSame('https://one.com.py/vls/app-debug.apk', config('app_release.apk_url'));
        $this->getJson('/api/v1/app/latest')
            ->assertOk()
            ->assertJsonPath('apk_url', 'https://one.com.py/vls/app-debug.apk');
    }

    public function test_no_requiere_device_key(): void
    {
        // Sin header X-Device-Key debe responder 200 (endpoint publico).
        $this->getJson('/api/v1/app/latest')->assertOk();
    }

    public function test_version_code_es_entero(): void
    {
        $res = $this->getJson('/api/v1/app/latest');
        $res->assertOk();
        $this->assertIsInt($res->json('version_code'));
    }
}
