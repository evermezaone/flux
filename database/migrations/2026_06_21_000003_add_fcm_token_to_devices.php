<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FLX REQ-0028: token FCM por equipo, para enviarle push y "despertarlo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('fcm_token', 512)->nullable()->after('device_key');
            $table->timestamp('fcm_token_at')->nullable()->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn(['fcm_token', 'fcm_token_at']);
        });
    }
};
