<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FLX REQ-0025: metadatos de ubicacion del cruce para el mapa.
 * - location_manual: si esta en true, el ajuste manual del operador tiene precedencia y el GPS del
 *   equipo (VLS-0021) NO sobreescribe lat/lng.
 * - location_accuracy_m: precision del ultimo fix GPS recibido.
 * - location_updated_at: cuando se actualizo la ubicacion (GPS o manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->boolean('location_manual')->default(false)->after('lng');
            $table->decimal('location_accuracy_m', 8, 2)->nullable()->after('location_manual');
            $table->timestamp('location_updated_at')->nullable()->after('location_accuracy_m');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['location_manual', 'location_accuracy_m', 'location_updated_at']);
        });
    }
};
