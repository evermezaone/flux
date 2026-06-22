<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| FLX-0035 / VLS-0043: canal de comando. `channel` = canal SOLICITADO al encolar (auto|fcm|poll);
| `exec_channel` = canal REAL por el que el equipo lo ejecutó (fcm|poll), reportado en el ack.
| Sirve para elegir el canal, evitar doble ejecución y ver por dónde se fue cada comando.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->string('channel', 8)->default('auto')->after('cmd');     // auto|fcm|poll (solicitado)
            $table->string('exec_channel', 8)->nullable()->after('result');  // fcm|poll (ejecucion real)
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn(['channel', 'exec_channel']);
        });
    }
};
