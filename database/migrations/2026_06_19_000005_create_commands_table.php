<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('cmd', 40);                      // snapshot|publish_clip|delete_clip|delete_all
            $table->json('params')->nullable();
            $table->enum('status', ['pending', 'sent', 'done', 'failed'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('picked_at')->nullable();     // cuando la app lo retira
            $table->timestamp('done_at')->nullable();       // cuando confirma (ack)

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commands');
    }
};
