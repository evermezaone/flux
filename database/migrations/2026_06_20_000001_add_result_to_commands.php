<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| REQ-0015: detalle de lo que respondio el dispositivo al ejecutar el comando (ack.result).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->string('result', 1000)->nullable()->after('done_at');
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn('result');
        });
    }
};
