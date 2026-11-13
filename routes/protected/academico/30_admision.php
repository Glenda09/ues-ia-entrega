<?php

use App\Http\Controllers\Academico\AdmisionController as A;
use Illuminate\Support\Facades\Route;

// EXP-01 Aspirante
Route::post('mi-perfil-aspirante', [A::class, 'vincularPerfil'])->middleware('auth.jwt')->name('aspirante.vincular');
Route::get('mi-borrador-ingreso', [A::class, 'miBorrador'])->middleware('auth.jwt:academico_mi_solicitud_gestionar')->name('borrador.mostrar');
Route::patch('mi-borrador-ingreso', [A::class, 'guardarBorrador'])->middleware('auth.jwt:academico_mi_solicitud_gestionar')->name('borrador.guardar');
Route::post('mi-borrador-ingreso/anexos', [A::class, 'adjuntar'])->middleware('auth.jwt:academico_mi_solicitud_gestionar')->name('borrador.anexos');
Route::post('convocatorias/{convocatoria}/mis-solicitudes', [A::class, 'enviar'])->middleware('auth.jwt:academico_mi_solicitud_gestionar')->name('solicitudes.enviar');
Route::get('mis-solicitudes', [A::class, 'misSolicitudes'])->middleware('auth.jwt:academico_mi_solicitud_gestionar')->name('mis-solicitudes.index');
Route::get('mis-solicitudes/{solicitud}', [A::class, 'miSolicitud'])->middleware('auth.jwt:academico_mi_solicitud_gestionar')->name('mis-solicitudes.mostrar');

// EXP-02 Gestor de expediente
Route::get('solicitudes', [A::class, 'solicitudes'])->middleware('auth.jwt:academico_solicitud_ver')->name('solicitudes.index');
Route::get('solicitudes/{solicitud}', [A::class, 'solicitud'])->middleware('auth.jwt:academico_solicitud_ver')->name('solicitudes.mostrar');
Route::get('solicitudes/{solicitud}/anexos/{anexo}', [A::class, 'anexo'])->whereNumber('anexo')->middleware('auth.jwt:academico_solicitud_ver')->name('solicitudes.anexos');
Route::post('solicitudes/{solicitud}/decisiones', [A::class, 'decidir'])->middleware('auth.jwt:academico_solicitud_decidir')->name('solicitudes.decisiones');
Route::post('solicitudes/{solicitud}/pago/revisiones', [A::class, 'revisarPago'])->middleware('auth.jwt:academico_solicitud_decidir')->name('solicitudes.pago');
