<?php

use App\Http\Controllers\Academico\IAController as I;
use Illuminate\Support\Facades\Route;

// IA-BASE-01 / IAA-01 / IAA-02 públicos
Route::get('ia/disponibilidad', [I::class, 'disponibilidadPublica'])->name('ia.disponibilidad');
Route::get('carreras', [I::class, 'carrerasPublicas'])->name('carreras.index');
Route::get('carreras/{carrera}', [I::class, 'carreraPublica'])->whereNumber('carrera')->name('carreras.mostrar');
Route::post('asistente-aspirantes/mensajes', [I::class, 'mensajePublico'])->name('asistente.mensajes');
Route::get('asistente-aspirantes/conversaciones', [I::class, 'conversacionesPublicas'])->name('asistente.conversaciones');
Route::get('asistente-aspirantes/conversaciones/{conversacion}/mensajes', [I::class, 'mensajesPublicos'])->whereUuid('conversacion')->name('asistente.mensajes.index');
