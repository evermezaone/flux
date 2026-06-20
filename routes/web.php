<?php

use App\Http\Controllers\Api\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Descarga de media desde el panel. Vive en web.php para usar la sesion web/Filament,
// pero conserva la URL /api/v1/... que ya guarda y genera el panel.
Route::middleware('auth')
    ->get('/api/v1/media/{media}/download', [MediaController::class, 'download'])
    ->name('media.download');
