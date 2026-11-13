<?php

use App\Http\Controllers\Academico\IAController as I;
use Illuminate\Support\Facades\Route;

// IA-BASE-01
Route::get('ia/disponibilidad', [I::class, 'disponibilidadAcademica'])->middleware('auth.jwt')->name('ia.disponibilidad');
Route::get('ia/consumo', [I::class, 'consumo'])->middleware('auth.jwt:academico_ia_consumo_ver')->name('ia.consumo');
Route::get('ia/tarifas', [I::class, 'tarifas'])->middleware('auth.jwt:academico_ia_tarifa_gestionar')->name('ia.tarifas.index');
Route::post('ia/tarifas', [I::class, 'crearTarifa'])->middleware('auth.jwt:academico_ia_tarifa_gestionar')->name('ia.tarifas.crear');

// IAA-01 Fuentes
Route::get('carreras/{carrera}/fuentes-ia', [I::class, 'fuentesCarrera'])->middleware('auth.jwt:academico_fuente_ia_ver')->name('fuentes-ia.index');
Route::post('carreras/{carrera}/fuentes-ia/publicaciones', [I::class, 'publicarFuentes'])->middleware('auth.jwt:academico_fuente_ia_publicar')->name('fuentes-ia.publicar');

// IAE-01 Asistente estudiantil
Route::post('mi-asistente/mensajes', [I::class, 'mensajeEstudiante'])->middleware('auth.jwt:academico_mi_asistente_usar')->name('mi-asistente.mensajes');
Route::get('mi-asistente/conversaciones', [I::class, 'conversacionesEstudiante'])->middleware('auth.jwt:academico_mi_asistente_usar')->name('mi-asistente.conversaciones');
Route::get('mi-asistente/conversaciones/{conversacion}/mensajes', [I::class, 'mensajesEstudiante'])->whereUuid('conversacion')->middleware('auth.jwt:academico_mi_asistente_usar')->name('mi-asistente.mensajes.index');
