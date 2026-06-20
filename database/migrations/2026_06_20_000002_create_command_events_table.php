<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| REQ-0015: bitacora del ciclo de vida de cada comando (trazabilidad FLX<->VLS).
| Un registro por transicion: created -> sent -> done|failed, con timestamp y nota.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('command_id')->constrained('commands')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('event', ['created', 'sent', 'done', 'failed']);
            $table->string('note', 1000)->nullable();   // detalle/result reportado por el dispositivo
            $table->timestamp('created_at')->useCurrent();

            $table->index('command_id');
            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_events');
    }
};
