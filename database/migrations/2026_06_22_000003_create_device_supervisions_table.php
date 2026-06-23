<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| FLX-0041: estado del supervisor remoto por equipo. Detecta ausencia de heartbeat y escala acciones
| (diagnostico/ping/reinicio) con anti-tormenta. Una fila por dispositivo.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_supervisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete()->unique();
            // online | degradado | sin_metricas | recuperando | requiere_intervencion
            $table->string('state', 24)->default('online');
            $table->unsignedTinyInteger('step')->default(0);     // paso de escalamiento actual
            $table->string('last_action', 32)->nullable();        // get_logs|ping|restart_app|restart_device
            $table->string('last_action_channel', 8)->nullable(); // fcm|poll
            $table->string('last_action_result', 255)->nullable();
            $table->timestamp('last_action_at')->nullable();
            $table->string('reason', 255)->nullable();            // por que del estado/accion (visible al operador)
            $table->timestamp('window_started_at')->nullable();   // ventana anti-tormenta
            $table->unsignedInteger('window_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_supervisions');
    }
};
