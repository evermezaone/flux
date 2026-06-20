<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| REQ-0020: configuracion POR EQUIPO. PK compuesta (device_id, key) - clave natural, sin id.
| Pisa a la global cuando coincide la key.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_settings', function (Blueprint $table) {
            $table->foreignId('device_id')->constrained('devices')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('key', 80);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();

            $table->primary(['device_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_settings');
    }
};
