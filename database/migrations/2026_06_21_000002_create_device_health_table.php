<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FLX REQ-0026: ultimo estado de salud por equipo (heartbeat del VLS-0022).
 * Una fila por dispositivo (upsert): overall + subsistemas + metricas + cuando reporto.
 * `alerted` da anti-spam para las alertas (avisar al cambiar de estado, no en cada chequeo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_health', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained('devices')->cascadeOnDelete();
            $table->string('overall', 16)->default('ok'); // ok | warn | fail
            $table->json('subsystems')->nullable();        // { storage:{status,detail}, ... }
            $table->json('device_metrics')->nullable();     // { battery_pct, temp_c, ... }
            $table->unsignedBigInteger('uptime_s')->nullable();
            $table->string('app_version', 32)->nullable();
            $table->unsignedInteger('app_build')->nullable();
            $table->timestamp('reported_at')->nullable();   // cuando el equipo envio el latido
            $table->boolean('alerted')->default(false);     // anti-spam de alertas
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_health');
    }
};
