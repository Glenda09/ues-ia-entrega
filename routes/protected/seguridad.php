<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Seguridad\PermisosController;
use App\Http\Controllers\Seguridad\GrupoAccionController;
use App\Http\Controllers\Seguridad\AccionController;
use App\Http\Controllers\Seguridad\PerfilController;
use App\Http\Controllers\Seguridad\MenuController;
use App\Http\Controllers\Seguridad\ModuloController;

Route::prefix('seguridad')->group(function () {
    Route::prefix('permisos')->group(function () {
        Route::get('/', [PermisosController::class, 'index'])
            ->middleware('auth.jwt:seguridad_permisos_index')
            ->name('protected.seguridad.permisos.index');

        Route::post('/', [PermisosController::class, 'guardar'])
            ->middleware('auth.jwt:seguridad_permisos_guardar')
            ->name('protected.seguridad.permisos.guardar');

        Route::get('{permiso}', [PermisosController::class, 'mostrar'])
            ->middleware('auth.jwt:seguridad_permisos_mostrar')
            ->name('protected.seguridad.permisos.mostrar');

        Route::put('{permiso}', [PermisosController::class, 'actualizar'])
            ->middleware('auth.jwt:seguridad_permisos_actualizar')
            ->name('protected.seguridad.permisos.actualizar');

        Route::delete('{permiso}', [PermisosController::class, 'eliminar'])
            ->middleware('auth.jwt:seguridad_permisos_eliminar')
            ->name('protected.seguridad.permisos.eliminar');
    });

    Route::prefix('grupo-accion')->group(function () {
        Route::get('/', [GrupoAccionController::class, 'index'])
            ->middleware('auth.jwt:seguridad_grupo_accion_index')
            ->name('protected.seguridad.grupo-accion.index');

        Route::post('/', [GrupoAccionController::class, 'guardar'])
            ->middleware('auth.jwt:seguridad_grupo_accion_guardar')
            ->name('protected.seguridad.grupo-accion.guardar');

        Route::get('{id_grupo}', [GrupoAccionController::class, 'mostrar'])
            ->middleware('auth.jwt:seguridad_grupo_accion_mostrar')
            ->name('protected.seguridad.grupo-accion.mostrar');

        Route::put('{id_grupo}', [GrupoAccionController::class, 'actualizar'])
            ->middleware('auth.jwt:seguridad_grupo_accion_actualizar')
            ->name('protected.seguridad.grupo-accion.actualizar');

        Route::delete('{id_grupo}', [GrupoAccionController::class, 'eliminar'])
            ->middleware('auth.jwt:seguridad_grupo_accion_eliminar')
            ->name('protected.seguridad.grupo-accion.eliminar');

        Route::put('{id_grupo}/estado', [GrupoAccionController::class, 'alternarEstado'])
            ->middleware('auth.jwt:seguridad_grupo_accion_estado')
            ->name('protected.seguridad.grupo-accion.alternar-estado');
    });

    Route::prefix('accion')->group(function () {
        Route::get('/', [AccionController::class, 'index'])
            ->middleware('auth.jwt:seguridad_accion_index')
            ->name('protected.seguridad.accion.index');

        Route::post('/', [AccionController::class, 'guardar'])
            ->middleware('auth.jwt:seguridad_accion_guardar')
            ->name('protected.seguridad.accion.guardar');

        Route::get('{id_accion}', [AccionController::class, 'mostrar'])
            ->middleware('auth.jwt:seguridad_accion_mostrar')
            ->name('protected.seguridad.accion.mostrar');

        Route::put('{id_accion}', [AccionController::class, 'actualizar'])
            ->middleware('auth.jwt:seguridad_accion_actualizar')
            ->name('protected.seguridad.accion.actualizar');

        Route::delete('{id_accion}', [AccionController::class, 'eliminar'])
            ->middleware('auth.jwt:seguridad_accion_eliminar')
            ->name('protected.seguridad.accion.eliminar');

        Route::put('{id_accion}/estado', [AccionController::class, 'alternarEstado'])
            ->middleware('auth.jwt:seguridad_accion_alternar_estado')
            ->name('protected.seguridad.accion.alternar-estado');

        Route::post('{id_accion}/permisos', [AccionController::class, 'asignarPermisos'])
            ->middleware('auth.jwt:seguridad_accion_permisos_asignar')
            ->name('protected.seguridad.accion.permisos.asignar');

        Route::put('{id_accion}/permisos', [AccionController::class, 'sincronizarPermisos'])
            ->middleware('auth.jwt:seguridad_accion_permisos_sincronizar')
            ->name('protected.seguridad.accion.permisos.sincronizar');

        Route::delete('{id_accion}/permisos', [AccionController::class, 'removerPermisos'])
            ->middleware('auth.jwt:seguridad_accion_permisos_remover')
            ->name('protected.seguridad.accion.permisos.remover');
    });

    Route::prefix('perfil')->group(function () {
        Route::get('/', [PerfilController::class, 'index'])
            ->middleware('auth.jwt:seguridad_perfil_index')
            ->name('protected.seguridad.perfil.index');

        Route::post('/', [PerfilController::class, 'guardar'])
            ->middleware('auth.jwt:seguridad_perfil_guardar')
            ->name('protected.seguridad.perfil.guardar');

        Route::get('{codigo}', [PerfilController::class, 'mostrar'])
            ->middleware('auth.jwt:seguridad_perfil_mostrar')
            ->name('protected.seguridad.perfil.mostrar');

        Route::put('{codigo}', [PerfilController::class, 'actualizar'])
            ->middleware('auth.jwt:seguridad_perfil_actualizar')
            ->name('protected.seguridad.perfil.actualizar');

        Route::delete('{codigo}', [PerfilController::class, 'eliminar'])
            ->middleware('auth.jwt:seguridad_perfil_eliminar')
            ->name('protected.seguridad.perfil.eliminar');

        Route::put('{codigo}/estado', [PerfilController::class, 'alternarEstado'])
            ->middleware('auth.jwt:seguridad_perfil_alternar_estado')
            ->name('protected.seguridad.perfil.alternar-estado');
    });

    Route::prefix('menu')->group(function () {
        Route::get('/', [MenuController::class, 'index'])
            ->middleware('auth.jwt:seguridad_menu_index')
            ->name('protected.seguridad.menu.index');

        Route::get('/padres', [MenuController::class, 'listarPadres'])
            ->middleware('auth.jwt:seguridad_menu_index')
            ->name('protected.seguridad.menu.padres');

        Route::post('/', [MenuController::class, 'guardar'])
            ->middleware('auth.jwt:seguridad_menu_guardar')
            ->name('protected.seguridad.menu.guardar');

        Route::get('{id_opcion}', [MenuController::class, 'mostrar'])
            ->middleware('auth.jwt:seguridad_menu_mostrar')
            ->name('protected.seguridad.menu.mostrar');

        Route::put('{id_opcion}', [MenuController::class, 'actualizar'])
            ->middleware('auth.jwt:seguridad_menu_actualizar')
            ->name('protected.seguridad.menu.actualizar');

        Route::delete('{id_opcion}', [MenuController::class, 'eliminar'])
            ->middleware('auth.jwt:seguridad_menu_eliminar')
            ->name('protected.seguridad.menu.eliminar');
    });

    Route::prefix('modulo')->group(function () {
        Route::get('/', [ModuloController::class, 'index'])
            ->middleware('auth.jwt:seguridad_menu_index')
            ->name('protected.seguridad.modulo.index');
    });
});
