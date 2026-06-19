<?php

use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\TelemetryQueryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Rutas de la API FLX (prefijo /api). Versionadas en /v1.
| Ingesta protegida por X-Device-Key (middleware 'device.key').
| Los endpoints reales de ingesta/consulta/comandos se agregan en REQ-0003..REQ-0005.
*/

Route::prefix('v1')->group(function () {

    // Ingesta (app VialSense -> backend): requiere X-Device-Key.
    Route::middleware('device.key')->group(function () {
        // Prueba de autenticacion del dispositivo (REQ-0002).
        Route::get('/ping', function (Request $request) {
            $device = $request->attributes->get('device');
            return response()->json([
                'ok' => true,
                'device' => $device->code,
                'site_id' => $device->site_id,
            ]);
        });

        // Ingesta (REQ-0003).
        Route::post('/telemetry', [TelemetryController::class, 'store']);
        Route::post('/media', [MediaController::class, 'store']);

        // Cola de comandos: lado dispositivo (REQ-0004).
        Route::get('/commands', [CommandController::class, 'pull']);
        Route::post('/commands/{command}/ack', [CommandController::class, 'ack']);
    });

    // Lado operador/panel. Auth de operador (sesion).
    Route::middleware('auth')->group(function () {
        // Cola de comandos: encolar (REQ-0004).
        Route::post('/commands', [CommandController::class, 'enqueue']);

        // Consulta para el panel (REQ-0005).
        Route::get('/sites', [SiteController::class, 'index']);
        Route::get('/devices', [DeviceController::class, 'index']);
        Route::get('/telemetry', [TelemetryQueryController::class, 'index']);
        Route::get('/summary', [TelemetryQueryController::class, 'summary']);
    });

});
