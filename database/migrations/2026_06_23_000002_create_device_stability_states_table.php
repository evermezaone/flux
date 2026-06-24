<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| FLX-0047: estado de estabilidad consolidado por equipo (agregados 24h + status + ultimo evento). Lo
| recalcula la ingesta y lo leen el panel y el supervisor de estabilidad (FLX-0048).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_stability_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('stability_status', 16)->default('ok'); // ok | warn | critical
            $table->unsignedSmallInteger('crash_count_24h')->default(0);
            $table->unsignedSmallInteger('anr_count_24h')->default(0);
            $table->unsignedSmallInteger('ui_freeze_count_24h')->default(0);
            $table->unsignedSmallInteger('app_error_count_24h')->default(0);   // FLX-0047 R1: application_error_suspected
            $table->unsignedSmallInteger('event_count_24h')->default(0);       // FLX-0047 R1: total accionable 24h
            $table->string('last_stability_event', 40)->nullable();
            $table->timestamp('last_stability_event_at')->nullable();
            $table->boolean('ui_frozen')->default(false);
            $table->timestamp('ui_last_tick_at')->nullable();
            $table->string('last_diagnostic_id', 80)->nullable();
            $table->string('alerted_status', 16)->nullable();                 // FLX-0047 R1: ultimo nivel avisado (anti-tormenta + escalado)
            // FLX-0048: estado de recuperacion automatica.
            $table->string('recovery_step', 24)->default('idle');             // idle|diagnostics|restart|reboot|hold
            $table->timestamp('recovery_started_at')->nullable();
            $table->string('last_recovery_action', 40)->nullable();
            $table->timestamp('last_recovery_action_at')->nullable();
            $table->unsignedSmallInteger('recovery_attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_stability_states');
    }
};
