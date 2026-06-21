<?php

use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\Api\CommandController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FcmController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapController;
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

    // Manifiesto de version de la app (publico, sin auth): auto-update de VLS (REQ-0014).
    Route::get('/app/latest', [AppVersionController::class, 'latest']);

    // Version del panel/web FLX (publico): verificar el deploy (REQ-0017).
    Route::get('/version', [AppVersionController::class, 'web']);

    // Healthcheck plano para monitores externos (REQ-0026): 200 / 503. Global o ?device=code.
    Route::get('/healthz', [HealthController::class, 'healthz']);

    // Ingesta (app VialSense -> backend): X-Device-Key + App Check (REQ-0029) + rate-limit (REQ-0009).
    Route::middleware(['device.key', 'appcheck', 'throttle:ingesta'])->group(function () {
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
        // Subida de clip a demanda (REQ-0008).
        Route::post('/media/upload', [MediaController::class, 'upload']);

        // Cola de comandos: lado dispositivo (REQ-0004).
        Route::get('/commands', [CommandController::class, 'pull']);
        Route::post('/commands/{command}/ack', [CommandController::class, 'ack']);

        // Config efectiva del equipo (global + overrides del device). REQ-0020.
        Route::get('/config', [ConfigController::class, 'show']);

        // Ubicacion GPS del equipo (REQ-0025; emisor VLS-0021). Respeta el ajuste manual del cruce.
        Route::post('/location', [LocationController::class, 'store']);

        // Heartbeat + autodiagnostico del equipo (REQ-0026; emisor VLS-0022).
        Route::post('/health', [HealthController::class, 'store']);

        // Registro del token FCM del equipo (REQ-0028; emisor VLS-0025).
        Route::post('/fcm-token', [FcmController::class, 'registerToken']);
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

        // Datos del mapa: cruces + ultima telemetria (vivo o ?at= historico). REQ-0025.
        Route::get('/map', [MapController::class, 'data'])->name('api.map');

    });

});
