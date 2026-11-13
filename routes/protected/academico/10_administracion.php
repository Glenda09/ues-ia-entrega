<?php

use App\Http\Controllers\Academico\AdministracionController as A;
use Illuminate\Support\Facades\Route;

// GEN-01 Docentes
Route::get('docentes', [A::class, 'docentes'])->middleware('auth.jwt:academico_docente_catalogo_ver')->name('docentes.index');
Route::post('docentes', [A::class, 'crearDocente'])->middleware('auth.jwt:academico_docente_catalogo_gestionar')->name('docentes.crear');
Route::get('docentes/{docente}', [A::class, 'docente'])->middleware('auth.jwt:academico_docente_catalogo_ver')->name('docentes.mostrar');
Route::patch('docentes/{docente}', [A::class, 'actualizarDocente'])->middleware('auth.jwt:academico_docente_catalogo_gestionar')->name('docentes.actualizar');
Route::get('cuentas', [A::class, 'cuentas'])->middleware('auth.jwt:academico_docente_catalogo_gestionar,academico_gestor_asignar,academico_documento_autoridad_asignar')->name('cuentas.buscar');

// GEN-02 Catálogos
Route::get('mis-carreras-gestionadas', [A::class, 'misCarreras'])->middleware('auth.jwt:academico_carrera_asignada_ver')->name('carreras.gestionadas');
foreach (['carreras', 'materias', 'sedes', 'facultades'] as $catalogo) {
    Route::get($catalogo, [A::class, 'listar'])->defaults('catalogo', $catalogo)->middleware('auth.jwt:academico_catalogo_ver')->name("$catalogo.index");
    Route::post($catalogo, [A::class, 'crear'])->defaults('catalogo', $catalogo)->middleware('auth.jwt:academico_catalogo_gestionar')->name("$catalogo.crear");
    Route::get("$catalogo/{id}", [A::class, 'mostrar'])->defaults('catalogo', $catalogo)->whereNumber('id')->middleware('auth.jwt:academico_catalogo_ver')->name("$catalogo.mostrar");
    Route::patch("$catalogo/{id}", [A::class, 'actualizar'])->defaults('catalogo', $catalogo)->whereNumber('id')->middleware('auth.jwt:academico_catalogo_gestionar')->name("$catalogo.actualizar");
}

// Gestores de carrera
Route::get('carreras/{carrera}/gestores', [A::class, 'gestores'])->middleware('auth.jwt:academico_gestor_asignar')->name('carreras.gestores.index');
Route::put('carreras/{carrera}/gestores/{usuario}', [A::class, 'asignarGestor'])->middleware('auth.jwt:academico_gestor_asignar')->name('carreras.gestores.asignar');
Route::delete('carreras/{carrera}/gestores/{usuario}', [A::class, 'revocarGestor'])->middleware('auth.jwt:academico_gestor_asignar')->name('carreras.gestores.revocar');

// GEN-03 Pensums
Route::get('carreras/{carrera}/pensums', [A::class, 'pensumsCarrera'])->middleware('auth.jwt:academico_pensum_ver')->name('pensums.index');
Route::post('carreras/{carrera}/pensums', [A::class, 'crearPensum'])->middleware('auth.jwt:academico_pensum_gestionar')->name('pensums.crear');
Route::get('pensums/{pensum}', [A::class, 'pensum'])->middleware('auth.jwt:academico_pensum_ver')->name('pensums.mostrar');
Route::patch('pensums/{pensum}', [A::class, 'actualizarPensum'])->middleware('auth.jwt:academico_pensum_gestionar')->name('pensums.actualizar');
Route::post('pensums/{pensum}/transiciones', [A::class, 'transicionPensum'])->middleware('auth.jwt:academico_pensum_gestionar')->name('pensums.transiciones');
