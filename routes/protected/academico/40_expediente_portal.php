<?php

use App\Http\Controllers\Academico\DocenciaController as D;
use App\Http\Controllers\Academico\EstudianteController as E;
use App\Http\Controllers\Academico\ExpedienteController as X;
use Illuminate\Support\Facades\Route;

// EXP-03 Expedientes
Route::get('expedientes', [X::class, 'index'])->middleware('auth.jwt:academico_expediente_ver')->name('expedientes.index');
Route::post('expedientes', [X::class, 'admisionDirecta'])->middleware('auth.jwt:academico_solicitud_decidir')->name('expedientes.admision-directa');
Route::get('expedientes/{expediente}', [X::class, 'mostrar'])->middleware('auth.jwt:academico_expediente_ver')->name('expedientes.mostrar');
Route::patch('expedientes/{expediente}', [X::class, 'actualizar'])->middleware('auth.jwt:academico_expediente_editar')->name('expedientes.actualizar');
Route::post('expedientes/{expediente}/transiciones', [X::class, 'transicion'])->middleware('auth.jwt:academico_expediente_estado')->name('expedientes.transiciones');
Route::post('expedientes/{expediente}/acceso/reenvios', [X::class, 'reenviarAcceso'])->middleware('auth.jwt:academico_expediente_editar')->name('expedientes.acceso.reenviar');

// EXP-04 Documentos y autoridad
Route::get('expedientes/{expediente}/documentos', [X::class, 'documentos'])->middleware('auth.jwt:academico_documento_ver')->name('documentos.index');
Route::post('expedientes/{expediente}/documentos', [X::class, 'emitir'])->middleware('auth.jwt:academico_documento_emitir')->name('documentos.emitir');
Route::get('expedientes/{expediente}/documentos/{documento}', [X::class, 'documento'])->middleware('auth.jwt:academico_documento_ver')->name('documentos.mostrar');
Route::get('expedientes/{expediente}/documentos/{documento}/archivo', [X::class, 'archivo'])->middleware('auth.jwt:academico_documento_ver')->name('documentos.archivo');
Route::get('documentos/autoridad', [X::class, 'autoridad'])->middleware('auth.jwt:academico_documento_autoridad_asignar')->name('documentos.autoridad.mostrar');
Route::put('documentos/autoridad', [X::class, 'asignarAutoridad'])->middleware('auth.jwt:academico_documento_autoridad_asignar')->name('documentos.autoridad.asignar');

// EST-01/02 Portal estudiantil
Route::get('mi-perfil', [E::class, 'miPerfil'])->middleware('auth.jwt:academico_mi_perfil_ver')->name('mi-perfil');
Route::get('mi-calendario', [E::class, 'miCalendario'])->middleware('auth.jwt:academico_mi_perfil_ver')->name('mi-calendario');
Route::get('mi-avance', [E::class, 'miAvance'])->middleware('auth.jwt:academico_mi_avance_ver')->name('mi-avance');

// INS-01/02 Inscripción
Route::get('mi-oferta', [E::class, 'miOferta'])->middleware('auth.jwt:academico_mi_oferta_ver')->name('mi-oferta');
Route::get('mis-inscripciones', [E::class, 'misInscripciones'])->middleware('auth.jwt:academico_mi_inscripcion_ver')->name('mis-inscripciones.index');
Route::post('mis-inscripciones', [E::class, 'confirmar'])->middleware('auth.jwt:academico_mi_inscripcion_gestionar')->name('mis-inscripciones.confirmar');
Route::post('mis-inscripciones/{inscripcion}/retiros', [E::class, 'retirar'])->middleware('auth.jwt:academico_mi_inscripcion_gestionar')->name('mis-inscripciones.retiros');

// DOC-01 Docencia (el grupo se resuelve por asignación vigente, no por binding)
Route::get('mis-grupos', [D::class, 'misGrupos'])->middleware('auth.jwt:academico_mi_grupo_ver')->name('mis-grupos.index');
Route::get('mis-grupos/{id_grupo}', [D::class, 'miGrupo'])->whereNumber('id_grupo')->middleware('auth.jwt:academico_mi_grupo_ver')->name('mis-grupos.mostrar');
Route::get('mis-grupos/{id_grupo}/estudiantes', [D::class, 'padron'])->whereNumber('id_grupo')->middleware('auth.jwt:academico_mi_grupo_ver')->name('mis-grupos.estudiantes');
Route::get('mis-grupos/{id_grupo}/evaluaciones', [D::class, 'evaluaciones'])->whereNumber('id_grupo')->middleware('auth.jwt:academico_mi_grupo_ver')->name('mis-grupos.evaluaciones');
Route::put('mis-grupos/{id_grupo}/evaluaciones', [D::class, 'guardarEvaluaciones'])->whereNumber('id_grupo')->middleware('auth.jwt:academico_mi_nota_gestionar')->name('mis-grupos.evaluaciones.guardar');
Route::put('mis-grupos/{id_grupo}/notas', [D::class, 'guardarNotas'])->whereNumber('id_grupo')->middleware('auth.jwt:academico_mi_nota_gestionar')->name('mis-grupos.notas');
Route::post('mis-grupos/{id_grupo}/calculo-final', [D::class, 'calculoFinal'])->whereNumber('id_grupo')->middleware('auth.jwt:academico_mi_nota_gestionar')->name('mis-grupos.calculo');

// DOC-02 Actas
Route::get('actas', [D::class, 'actas'])->middleware('auth.jwt:academico_acta_ver')->name('actas.index');
Route::get('actas/{grupo}', [D::class, 'acta'])->middleware('auth.jwt:academico_acta_ver')->name('actas.mostrar');
Route::post('actas/{grupo}/publicaciones', [D::class, 'publicar'])->middleware('auth.jwt:academico_acta_publicar')->name('actas.publicar');

// GEN-04 Ciclos institucionales
Route::get('ciclos-institucionales', [D::class, 'ciclosInstitucionales'])->middleware('auth.jwt:academico_ciclo_institucional_ver')->name('ciclos-institucionales.index');
Route::get('ciclos-institucionales/{ciclo}', [D::class, 'cicloInstitucional'])->middleware('auth.jwt:academico_ciclo_institucional_ver')->name('ciclos-institucionales.mostrar');
