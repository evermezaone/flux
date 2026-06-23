<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| FLX-0044: ultimo estado de prerequisitos operativos por equipo (de device_metrics.operational_requirements,
| que produce VLS-0063). Anti-tormenta: se persiste y se detectan CAMBIOS de estado (no se re-alerta cada
| heartbeat). failing_since = duracion del fallo actual.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_requirement_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('ok')->default(true);                  // sin fallos criticos
            $table->unsignedSmallInteger('critical_count')->default(0);
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->json('failures')->nullable();                   // lista resumida de checks fallando
            $table->timestamp('failing_since')->nullable();         // desde cuando falla (duracion)
            $table->timestamp('last_changed_at')->nullable();       // ultimo cambio de conjunto de fallos
            $table->timestamp('last_recovery_at')->nullable();      // ultima vez que volvio a OK
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_requirement_states');
    }
};
