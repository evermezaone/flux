<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indice (device_id, ts) para resolver el ultimo snapshot de salud por dispositivo
 * de forma eficiente (REQ-0005, Obs 157). Aditivo; no altera el esquema cerrado de REQ-0001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry', function (Blueprint $table) {
            $table->index(['device_id', 'ts'], 'telemetry_device_id_ts_index');
        });
    }

    public function down(): void
    {
        Schema::table('telemetry', function (Blueprint $table) {
            $table->dropIndex('telemetry_device_id_ts_index');
        });
    }
};
