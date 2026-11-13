<?php

use App\Http\Controllers\Web\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/home', [PageController::class, 'mostrar'])->defaults('page', 'landing')->name('landingPage.index');
Route::get('/login', [PageController::class, 'mostrar'])->defaults('page', 'login')->name('auth.login');
Route::get('/configurar-doble-factor', [PageController::class, 'mostrar'])->defaults('page', 'configurar-doble-factor')->name('auth.configurar-2fa');
Route::get('/two-factor', [PageController::class, 'mostrar'])->defaults('page', 'two-factor')->name('auth.two-factor');
Route::get('/forgot-password', [PageController::class, 'mostrar'])->defaults('page', 'forgot-password')->name('auth.forgot-password');

Route::get('/', [PageController::class, 'mostrar'])->defaults('page', 'dashboard')->name('dashboard.index');
Route::get('/theme-preview', [PageController::class, 'mostrar'])->defaults('page', 'theme-preview')->name('dashboard.themePreview');
Route::get('/personalizacion', [PageController::class, 'mostrar'])->defaults('page', 'personalizacion')->name('personalizacion.index');
Route::get('/perfil', [PageController::class, 'mostrar'])->defaults('page', 'profile')->name('personal.perfil');
Route::get('/auth/usuarios', [PageController::class, 'mostrar'])->defaults('page', 'users')->name('auth.usuarios');
Route::get('/seguridad/perfiles', [PageController::class, 'mostrar'])->defaults('page', 'profiles')->name('seguridad.perfiles');
Route::get('/seguridad/acciones', [PageController::class, 'mostrar'])->defaults('page', 'actions')->name('seguridad.acciones');
Route::get('/resources/apikeys', [PageController::class, 'mostrar'])->defaults('page', 'api-keys')->name('resources.apikeys');
Route::get('/root/permisos', [PageController::class, 'mostrar'])->defaults('page', 'permissions')->name('root.permisos');
Route::get('/root/menu', [PageController::class, 'mostrar'])->defaults('page', 'menu')->name('root.menu');
Route::get('/root/logs', [PageController::class, 'mostrar'])->defaults('page', 'logs')->name('root.logs');
Route::get('/root/request-logs', [PageController::class, 'mostrar'])->defaults('page', 'request-logs')->name('root.request.logs');
Route::get('/root/jobs', [PageController::class, 'mostrar'])->defaults('page', 'jobs')->name('root.jobs');
Route::get('/not-found', [PageController::class, 'mostrar'])->defaults('page', 'not-found')->name('not-found');

// Módulo académico (rutas MVC antes del fallback)
Route::get('/academico/configuracion', [PageController::class, 'mostrar'])->defaults('page', 'academico-configuracion')->name('web.academico-configuracion');
Route::get('/academico/administracion/docentes', [PageController::class, 'mostrar'])->defaults('page', 'academico-docentes')->name('web.academico-docentes');
Route::get('/academico/administracion/carreras', [PageController::class, 'mostrar'])->defaults('page', 'academico-carreras')->name('web.academico-carreras');
Route::get('/academico/administracion/materias', [PageController::class, 'mostrar'])->defaults('page', 'academico-materias')->name('web.academico-materias');
Route::get('/academico/administracion/sedes', [PageController::class, 'mostrar'])->defaults('page', 'academico-sedes')->name('web.academico-sedes');
Route::get('/academico/administracion/carreras/{carrera}/pensums', [PageController::class, 'mostrar'])->defaults('page', 'academico-pensums')->where(['carrera' => '[0-9]+'])->name('web.academico-pensums');
Route::get('/academico/administracion/ciclos', [PageController::class, 'mostrar'])->defaults('page', 'academico-ciclos-institucionales')->name('web.academico-ciclos-institucionales');
Route::get('/academico/autoridad', [PageController::class, 'mostrar'])->defaults('page', 'academico-autoridad')->name('web.academico-autoridad');
Route::get('/academico/gestion-carreras', [PageController::class, 'mostrar'])->defaults('page', 'academico-gestion-carreras')->name('web.academico-gestion-carreras');
Route::get('/academico/gestion-carreras/{carrera}/ciclos/{ciclo}', [PageController::class, 'mostrar'])->defaults('page', 'academico-ciclo')->where(['carrera' => '[0-9]+', 'ciclo' => '[0-9]+'])->name('web.academico-ciclo');
Route::get('/academico/gestion-carreras/{carrera}/convocatorias', [PageController::class, 'mostrar'])->defaults('page', 'academico-convocatorias')->where(['carrera' => '[0-9]+'])->name('web.academico-convocatorias');
Route::get('/academico/actas', [PageController::class, 'mostrar'])->defaults('page', 'academico-actas')->name('web.academico-actas');
Route::get('/academico/actas/{grupo}', [PageController::class, 'mostrar'])->defaults('page', 'academico-acta')->where(['grupo' => '[0-9]+'])->name('web.academico-acta');
Route::get('/academico/solicitudes', [PageController::class, 'mostrar'])->defaults('page', 'academico-solicitudes')->name('web.academico-solicitudes');
Route::get('/academico/solicitudes/{solicitud}', [PageController::class, 'mostrar'])->defaults('page', 'academico-solicitud')->where(['solicitud' => '[0-9]+'])->name('web.academico-solicitud');
Route::get('/academico/expedientes', [PageController::class, 'mostrar'])->defaults('page', 'academico-expedientes')->name('web.academico-expedientes');
Route::get('/academico/expedientes/{expediente}', [PageController::class, 'mostrar'])->defaults('page', 'academico-expediente')->where(['expediente' => '[0-9]+'])->name('web.academico-expediente');
Route::get('/docente/grupos', [PageController::class, 'mostrar'])->defaults('page', 'docente-grupos')->name('web.docente-grupos');
Route::get('/docente/grupos/{grupo}', [PageController::class, 'mostrar'])->defaults('page', 'docente-grupo')->where(['grupo' => '[0-9]+'])->name('web.docente-grupo');
Route::get('/docente/grupos/{grupo}/notas', [PageController::class, 'mostrar'])->defaults('page', 'docente-notas')->where(['grupo' => '[0-9]+'])->name('web.docente-notas');
Route::get('/estudiante', [PageController::class, 'mostrar'])->defaults('page', 'estudiante-inicio')->name('web.estudiante-inicio');
Route::get('/estudiante/avance', [PageController::class, 'mostrar'])->defaults('page', 'estudiante-avance')->name('web.estudiante-avance');
Route::get('/estudiante/inscripcion', [PageController::class, 'mostrar'])->defaults('page', 'estudiante-inscripcion')->name('web.estudiante-inscripcion');
Route::get('/estudiante/mis-inscripciones', [PageController::class, 'mostrar'])->defaults('page', 'estudiante-mis-inscripciones')->name('web.estudiante-mis-inscripciones');
Route::get('/estudiante/asistente', [PageController::class, 'mostrar'])->defaults('page', 'estudiante-asistente')->name('web.estudiante-asistente');
Route::get('/estudiante/cambiar-password-inicial', [PageController::class, 'mostrar'])->defaults('page', 'estudiante-cambiar-password')->name('web.estudiante-cambiar-password');
Route::get('/admision', [PageController::class, 'mostrar'])->defaults('page', 'admision')->name('web.admision');
Route::get('/admision/registro', [PageController::class, 'mostrar'])->defaults('page', 'admision-registro')->name('web.admision-registro');
Route::get('/admision/convocatorias/{convocatoria}', [PageController::class, 'mostrar'])->defaults('page', 'admision-formulario')->where(['convocatoria' => '[0-9]+'])->name('web.admision-formulario');
Route::get('/admision/correccion', [PageController::class, 'mostrar'])->defaults('page', 'admision-correccion')->name('web.admision-correccion');
Route::get('/admision/mis-solicitudes', [PageController::class, 'mostrar'])->defaults('page', 'admision-mis-solicitudes')->name('web.admision-mis-solicitudes');
Route::get('/asistente-aspirantes', [PageController::class, 'mostrar'])->defaults('page', 'asistente-aspirantes')->name('web.asistente-aspirantes');
Route::get('/carreras', [PageController::class, 'mostrar'])->defaults('page', 'carreras')->name('web.carreras');
Route::get('/carreras/{carrera}', [PageController::class, 'mostrar'])->defaults('page', 'carrera')->where(['carrera' => '[0-9]+'])->name('web.carrera');

Route::fallback([PageController::class, 'noEncontrada'])->name('web.fallback');
