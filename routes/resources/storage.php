<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Ejemplos\GcsController;

// Subir archivo (multipart/form-data, campo 'archivo')
Route::post('/guardar', [GcsController::class, 'guardar'])->name('resources.storage.gcs.guardar');

// Descargar por nombre (incluya subcarpetas si aplica)
Route::get('/descargar/{nombre}', [GcsController::class, 'descargar'])->where('nombre', '.*')->name('resources.storage.gcs.descargar');

// Eliminar por nombre
Route::delete('/eliminar/{nombre}', [GcsController::class, 'eliminar'])->where('nombre', '.*')->name('resources.storage.gcs.eliminar');

