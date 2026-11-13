<?php

use App\Http\Controllers\Academico\ConfiguracionController;
use Illuminate\Support\Facades\Route;

// BASE-02 Configuración general
Route::get('configuracion', [ConfiguracionController::class, 'mostrar'])
    ->middleware('auth.jwt:academico_configuracion_ver')->name('configuracion.mostrar');
Route::put('configuracion', [ConfiguracionController::class, 'actualizar'])
    ->middleware('auth.jwt:academico_configuracion_editar')->name('configuracion.actualizar');
Route::get('configuracion/revisiones', [ConfiguracionController::class, 'revisiones'])
    ->middleware('auth.jwt:academico_configuracion_ver')->name('configuracion.revisiones');
Route::post('configuracion/escalas', [ConfiguracionController::class, 'crearEscala'])
    ->middleware('auth.jwt:academico_configuracion_editar')->name('configuracion.escalas.crear');
