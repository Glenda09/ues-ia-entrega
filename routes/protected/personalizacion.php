<?php

use App\Http\Controllers\Personalizacion\PersonalizacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('personalizacion')->group(function () {
    Route::get('/', [PersonalizacionController::class, 'mostrar'])->middleware('auth.jwt:personalizacion_ver')->name('protected.personalizacion.mostrar');
    Route::put('/', [PersonalizacionController::class, 'guardar'])->middleware('auth.jwt:personalizacion_editar')->name('protected.personalizacion.guardar');
    Route::post('assets/{slot}', [PersonalizacionController::class, 'subir'])
        ->middleware('auth.jwt:personalizacion_editar')->name('protected.personalizacion.subir');
    Route::delete('assets/{slot}', [PersonalizacionController::class, 'quitar'])
        ->middleware('auth.jwt:personalizacion_editar')->name('protected.personalizacion.quitar');
});
