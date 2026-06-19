<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('device_id')->constrained('devices')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnUpdate()->restrictOnDelete();
            $table->dateTime('ts');                         // UTC
            $table->unsignedBigInteger('client_seq');       // idempotencia por dispositivo

            // --- Trafico ---
            $table->string('zone', 40)->nullable();
            $table->integer('occupancy')->nullable();
            $table->decimal('queue_len_m', 8, 2)->nullable();
            $table->integer('pressure')->nullable();
            $table->enum('congestion', ['low', 'med', 'high', 'saturated'])->nullable();
            $table->string('decision', 40)->nullable();     // texto libre acotado (A_green/B_green/...)
            $table->decimal('wait_est_s', 8, 2)->nullable();
            $table->integer('empty_s')->nullable();

            // --- Salud del equipo ---
            $table->unsignedTinyInteger('battery_pct')->nullable();
            $table->decimal('temp_c', 5, 2)->nullable();
            $table->unsignedTinyInteger('cpu_pct')->nullable();
            $table->unsignedTinyInteger('mem_pct')->nullable();
            $table->unsignedTinyInteger('storage_free_pct')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['device_id', 'client_seq']);    // idempotencia de ingesta
            $table->index(['site_id', 'ts']);               // consultas del panel
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry');
    }
};
