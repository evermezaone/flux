<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_falla_sin_admin_password(): void
    {
        // phpunit.xml deja ADMIN_EMAIL/ADMIN_PASSWORD vacios:
        // el seeder debe fallar en vez de crear un operador con clave conocida.
        $this->expectException(\RuntimeException::class);
        $this->seed(DatabaseSeeder::class);
    }
}
