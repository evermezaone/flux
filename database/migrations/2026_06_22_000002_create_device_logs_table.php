<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| FLX-0039 / VLS-0054: paquetes de logs subidos por el equipo (VLS/Sentinel) para diagnosticar fallos en
| campo (restart device, kiosk, foreground, FCM, etc.). El archivo vive en storage privado; aca solo va
| la metadata + la ruta. No se guardan secretos completos (eso lo garantiza el equipo al armar el paquete).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16)->default('vls');   // vls | sentinel | combined | system
            $table->string('build', 64)->nullable();         // versionName/Code del equipo
            $table->string('summary', 500)->nullable();      // resumen corto opcional
            $table->string('path', 255);                     // ruta en el disco privado 'local'
            $table->unsignedBigInteger('size')->default(0);  // bytes
            $table->timestamp('reported_at')->nullable();    // cuando lo armo el equipo
            $table->timestamps();

            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_logs');
    }
};
