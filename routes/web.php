<?php

use App\Http\Controllers\Api\DeviceLogController;
use App\Http\Controllers\Api\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Media desde el panel. Vive en web.php para usar la sesion web/Filament,
// pero conserva la URL /api/v1/... que ya guarda y genera el panel.
Route::middleware('auth')->group(function () {
    Route::get('/api/v1/media/{media}/download', [MediaController::class, 'download'])
        ->name('media.download');
    Route::get('/api/v1/media/{media}/view', [MediaController::class, 'view'])
        ->name('media.view');

    // FLX-0039: descarga de un paquete de logs del equipo por el operador (storage privado).
    Route::get('/api/v1/device-logs/{deviceLog}/download', [DeviceLogController::class, 'download'])
        ->name('device-logs.download');
});
