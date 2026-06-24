<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| FLX-0047: eventos de estabilidad enviados por VLS/Sentinel (crash, ANR sospechado, UI congelada,
| Application Error sospechado, recuperaciones). Idempotente por (device_id, event_id).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stability_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('event_id', 80);                  // id externo generado por el equipo
            $table->string('event_type', 40);                // crash | anr_suspected | ui_frozen_timeout | app_error_suspected (canonico; alias application_error_suspected) | activity_relaunch_loop | sentinel_oem_hibernation_suspected | recovery_escalated_to_reboot
            $table->string('severity', 16)->default('warn'); // warn | critical
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('recovered_at')->nullable();   // null = sigue activo
            $table->string('app_version', 40)->nullable();
            $table->string('sentinel_version', 40)->nullable();
            $table->string('summary', 255)->nullable();
            $table->json('details')->nullable();
            $table->string('diagnostic_id', 80)->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'event_id']);       // idempotencia de ingesta
            $table->index(['device_id', 'occurred_at']);     // agregados por ventana
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stability_events');
    }
};
