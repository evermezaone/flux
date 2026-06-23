<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| FLX-0045: idempotencia de ingesta por HASH de contenido en vez de por client_seq.
|
| Motivo: client_seq es un contador del telefono; al reinstalar la app vuelve a empezar bajo y reusa numeros
| que ya existen en la BD -> insertOrIgnore los descarta como "duplicados" aunque la lectura sea nueva
| (telemetria que llega pero no se registra). El hash incluye TODOS los campos del registro, asi un reintento
| del mismo registro deduplica y dos lecturas distintas siempre entran (reset-proof, sin colision por segundo).
|
| Se reemplaza el indice unico (device_id, client_seq) por (device_id, client_hash). client_seq queda como
| columna normal (orden/diagnostico).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry', function (Blueprint $table) {
            // sha256 hex = 64 chars. Nullable para filas historicas previas a la migracion.
            $table->char('client_hash', 64)->nullable()->after('client_seq');
        });

        Schema::table('telemetry', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'client_seq']); // ya no es la clave de idempotencia
            $table->index(['device_id', 'client_seq']);      // se conserva para orden/diagnostico
            $table->unique(['device_id', 'client_hash']);    // nueva idempotencia por contenido
        });
    }

    public function down(): void
    {
        Schema::table('telemetry', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'client_hash']);
            $table->dropIndex(['device_id', 'client_seq']);
            $table->unique(['device_id', 'client_seq']);
            $table->dropColumn('client_hash');
        });
    }
};
