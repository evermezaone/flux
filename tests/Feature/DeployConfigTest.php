<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployConfigTest extends TestCase
{
    public function test_env_example_tiene_claves_y_sin_secretos(): void
    {
        $path = base_path('.env.example');
        $this->assertFileExists($path);
        $content = file_get_contents($path);

        foreach ([
            'DB_DATABASE', 'DB_USERNAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD',
            'CORS_ALLOWED_ORIGINS', 'INGESTA_RATE_LIMIT', 'TELEMETRY_RETENTION_DAYS',
        ] as $key) {
            $this->assertStringContainsString($key, $content, "Falta {$key} en .env.example");
        }

        // No debe contener el secreto real del .env ni una contrasenia de admin embebida.
        $this->assertStringNotContainsString('FlxOper2026', $content);
        $this->assertMatchesRegularExpression('/^ADMIN_PASSWORD=\s*$/m', $content, 'ADMIN_PASSWORD debe ir vacio en .env.example');
    }

    public function test_readme_documenta_el_despliegue(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertStringContainsString('public/', $readme);       // docroot
        $this->assertStringContainsString('schedule:run', $readme);  // cron
        $this->assertStringContainsString('migrate', $readme);       // pasos

        // Endurecimiento de produccion (Obs 161).
        $this->assertStringContainsString('APP_ENV=production', $readme);
        $this->assertStringContainsString('APP_DEBUG=false', $readme);
        $this->assertStringContainsString('APP_URL', $readme);
    }
}
