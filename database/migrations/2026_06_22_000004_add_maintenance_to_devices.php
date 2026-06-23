<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| FLX-0043: mantenimiento preventivo. Fecha de instalacion (para edad operativa + reemplazo programado) y
| tipo de alimentacion manual (solar/respaldo/red), porque VLS no siempre puede medir la fuente.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->date('install_date')->nullable()->after('model');
            $table->string('power_source', 16)->nullable()->after('install_date'); // solar|backup|grid
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['install_date', 'power_source']);
        });
    }
};
