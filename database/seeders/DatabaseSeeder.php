<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Crea/actualiza el operador admin del panel. Credenciales SOLO desde .env, sin defaults.
     * Falla explicitamente si faltan ADMIN_EMAIL/ADMIN_PASSWORD (nunca siembra una clave conocida).
     * Idempotente: re-sembrar actualiza nombre/clave desde .env.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            throw new \RuntimeException(
                'No se siembra el operador: definir ADMIN_EMAIL y ADMIN_PASSWORD en .env '
                .'(sin valores por defecto, para no crear una clave conocida).'
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Operador FLX'), // nombre no es secreto
                'password' => Hash::make($password),
            ]
        );
    }
}
