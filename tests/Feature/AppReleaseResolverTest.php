<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FLX-0037: GET /api/v1/app/latest resuelve el manifiesto OTA en cadena
 * (archivo local -> HTTP latest.json -> fallback .env), para publicar sin tocar el .env.
 */
class AppReleaseResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_resuelve_desde_archivo_local_si_existe(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rel');
        file_put_contents($path, json_encode([
            'version_code' => 78078055,
            'version_name' => '2026.06.22-1620Z',
            'apk_url' => 'https://one.com.py/vls/app-debug.apk',
            'notes' => 'desde archivo',
        ]));
        config()->set('app_release.manifest_path', $path);
        config()->set('app_release.manifest_url', '');

        $res = $this->getJson('/api/v1/app/latest');

        $res->assertOk()
            ->assertJsonPath('version_code', 78078055)
            ->assertJsonPath('version_name', '2026.06.22-1620Z')
            ->assertHeader('X-Release-Source', 'file');

        @unlink($path);
    }

    public function test_resuelve_desde_http_si_no_hay_archivo(): void
    {
        config()->set('app_release.manifest_path', '');
        config()->set('app_release.manifest_url', 'https://one.com.py/vls/latest.json');
        Http::fake([
            'https://one.com.py/vls/latest.json' => Http::response([
                'version_code' => 99,
                'version_name' => '2026.07.01-0900Z',
                'apk_url' => 'https://one.com.py/vls/app-debug.apk',
                'notes' => 'desde http',
            ], 200),
        ]);

        $res = $this->getJson('/api/v1/app/latest');

        $res->assertOk()
            ->assertJsonPath('version_code', 99)
            ->assertJsonPath('notes', 'desde http')
            ->assertHeader('X-Release-Source', 'url');
    }

    public function test_fallback_a_env_si_archivo_y_http_fallan(): void
    {
        config()->set('app_release.version_code', 17);
        config()->set('app_release.version_name', '1.7');
        config()->set('app_release.apk_url', 'https://one.com.py/vls/app-debug.apk');
        config()->set('app_release.notes', 'env');
        config()->set('app_release.manifest_path', '/ruta/que/no/existe/latest.json');
        config()->set('app_release.manifest_url', 'https://one.com.py/vls/latest.json');
        Http::fake(['https://one.com.py/vls/latest.json' => Http::response('', 404)]);

        $res = $this->getJson('/api/v1/app/latest');

        $res->assertOk()
            ->assertJsonPath('version_code', 17)
            ->assertHeader('X-Release-Source', 'env');
    }

    public function test_json_invalido_cae_a_env(): void
    {
        config()->set('app_release.version_code', 17);
        config()->set('app_release.manifest_path', '');
        config()->set('app_release.manifest_url', 'https://one.com.py/vls/latest.json');
        Http::fake(['https://one.com.py/vls/latest.json' => Http::response('no-es-json', 200)]);

        $res = $this->getJson('/api/v1/app/latest');

        $res->assertOk()
            ->assertJsonPath('version_code', 17)
            ->assertHeader('X-Release-Source', 'env');
    }

    public function test_manifiesto_sin_version_code_valido_cae_a_env(): void
    {
        // version_code <= 0 o apk_url vacio = invalido (mismo criterio que la app).
        config()->set('app_release.version_code', 17);
        config()->set('app_release.manifest_path', '');
        config()->set('app_release.manifest_url', 'https://one.com.py/vls/latest.json');
        Http::fake(['https://one.com.py/vls/latest.json' => Http::response([
            'version_code' => 0, 'apk_url' => '',
        ], 200)]);

        $this->getJson('/api/v1/app/latest')
            ->assertOk()
            ->assertJsonPath('version_code', 17)
            ->assertHeader('X-Release-Source', 'env');
    }

    public function test_archivo_tiene_prioridad_sobre_http(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rel');
        file_put_contents($path, json_encode([
            'version_code' => 500,
            'version_name' => 'file',
            'apk_url' => 'https://one.com.py/vls/app-debug.apk',
            'notes' => '',
        ]));
        config()->set('app_release.manifest_path', $path);
        config()->set('app_release.manifest_url', 'https://one.com.py/vls/latest.json');
        Http::fake(['https://one.com.py/vls/latest.json' => Http::response([
            'version_code' => 1, 'apk_url' => 'https://x/app.apk',
        ], 200)]);

        $this->getJson('/api/v1/app/latest')
            ->assertOk()
            ->assertJsonPath('version_code', 500)
            ->assertHeader('X-Release-Source', 'file');

        @unlink($path);
    }

    public function test_cuerpo_mantiene_las_cuatro_claves_del_contrato(): void
    {
        // Aunque el manifiesto traiga extras, el cuerpo no cambia (la app ignora extras; el origen va en header).
        config()->set('app_release.manifest_path', '');
        config()->set('app_release.manifest_url', 'https://one.com.py/vls/latest.json');
        Http::fake(['https://one.com.py/vls/latest.json' => Http::response([
            'version_code' => 99,
            'version_name' => 'x',
            'apk_url' => 'https://one.com.py/vls/app-debug.apk',
            'notes' => 'n',
            'extra' => 'ignorame',
        ], 200)]);

        $res = $this->getJson('/api/v1/app/latest');
        $res->assertOk();
        $this->assertSame(
            ['version_code', 'version_name', 'apk_url', 'notes'],
            array_keys($res->json())
        );
    }
}
