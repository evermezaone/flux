<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('tipo', ['timelapse', 'clip']);
            $table->dateTime('ts_start')->nullable();
            $table->dateTime('ts_end')->nullable();
            $table->string('file')->unique();               // upsert por file
            $table->integer('fps')->nullable();
            $table->decimal('size_mb', 10, 2)->nullable();
            $table->boolean('available')->default(true);
            $table->string('url')->nullable();              // si se sube el archivo
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'ts_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
