<?php

use App\Http\Controllers\Academico\GestionCarreraController as G;
use Illuminate\Support\Facades\Route;

// CAR-01 Ciclos
Route::get('carreras/{carrera}/ciclos', [G::class, 'ciclos'])->middleware('auth.jwt:academico_ciclo_ver')->name('ciclos.index');
Route::get('carreras/{carrera}/ciclos/{ciclo}', [G::class, 'ciclo'])->middleware('auth.jwt:academico_ciclo_ver')->name('ciclos.mostrar');
Route::post('carreras/{carrera}/ciclos', [G::class, 'crearCiclo'])->middleware('auth.jwt:academico_ciclo_gestionar')->name('ciclos.crear');
Route::patch('ciclos/{ciclo}', [G::class, 'actualizarCiclo'])->middleware('auth.jwt:academico_ciclo_gestionar')->name('ciclos.actualizar');
Route::post('ciclos/{ciclo}/transiciones', [G::class, 'transicionCiclo'])->middleware('auth.jwt:academico_ciclo_gestionar')->name('ciclos.transiciones');

// CAR-02 Oferta y grupos
Route::get('ciclos/{ciclo}/ofertas', [G::class, 'ofertas'])->middleware('auth.jwt:academico_oferta_ver')->name('ofertas.index');
Route::post('ciclos/{ciclo}/ofertas', [G::class, 'crearOferta'])->middleware('auth.jwt:academico_oferta_gestionar')->name('ofertas.crear');
Route::post('ofertas/{oferta}/grupos', [G::class, 'crearGrupo'])->middleware('auth.jwt:academico_oferta_gestionar')->name('grupos.crear');
Route::patch('grupos/{grupo}', [G::class, 'actualizarGrupo'])->middleware('auth.jwt:academico_oferta_gestionar')->name('grupos.actualizar');

// CAR-03 Convocatorias
Route::get('carreras/{carrera}/convocatorias', [G::class, 'convocatorias'])->middleware('auth.jwt:academico_convocatoria_ver')->name('convocatorias.index');
Route::post('carreras/{carrera}/convocatorias', [G::class, 'crearConvocatoria'])->middleware('auth.jwt:academico_convocatoria_gestionar')->name('convocatorias.crear');
Route::get('convocatorias/{convocatoria}', [G::class, 'convocatoria'])->middleware('auth.jwt:academico_convocatoria_ver')->name('convocatorias.mostrar');
Route::patch('convocatorias/{convocatoria}', [G::class, 'actualizarConvocatoria'])->middleware('auth.jwt:academico_convocatoria_gestionar')->name('convocatorias.actualizar');
