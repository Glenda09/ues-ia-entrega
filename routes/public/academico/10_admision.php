<?php

use App\Http\Controllers\Academico\AdmisionController as Adm;
use App\Http\Controllers\Academico\PublicoController as P;
use Illuminate\Support\Facades\Route;

// EXP-01 Información pública y registro verificado
Route::get('convocatorias', [P::class, 'convocatorias'])->name('convocatorias.index');
Route::get('convocatorias/{convocatoria}', [P::class, 'convocatoria'])->name('convocatorias.mostrar');
Route::post('admision/verificaciones', [Adm::class, 'verificacion'])->middleware('throttle:20,1')->name('admision.verificaciones');
Route::post('admision/registros', [Adm::class, 'registro'])->middleware('throttle:20,1')->name('admision.registros');

// EXP-02 Corrección con token de un solo uso
Route::post('correcciones', [Adm::class, 'correccion'])->middleware('throttle:20,1')->name('correcciones');
