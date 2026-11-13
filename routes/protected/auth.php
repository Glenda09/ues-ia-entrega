<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\UsuarioController;
use App\Http\Controllers\Auth\ApiKeyController;

Route::prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('protected.auth.logout');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('protected.auth.change-password');

    Route::prefix('usuario')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])
            ->middleware('auth.jwt:auth_usuario_index')
            ->name('protected.auth.usuario.index');

        Route::post('/', [UsuarioController::class, 'guardar'])
            ->middleware('auth.jwt:auth_usuario_guardar')
            ->name('protected.auth.usuario.guardar');

        Route::get('{id_usuario}', [UsuarioController::class, 'mostrar'])
            ->middleware('auth.jwt:auth_usuario_mostrar')
            ->name('protected.auth.usuario.mostrar');

        Route::put('{id_usuario}', [UsuarioController::class, 'actualizar'])
            ->middleware('auth.jwt:auth_usuario_actualizar')
            ->name('protected.auth.usuario.actualizar');

        Route::post('{id_usuario}/restablecer-password', [UsuarioController::class, 'restablecerPassword'])
            ->middleware('auth.jwt:auth_usuario_restablecer_password')
            ->name('protected.auth.usuario.restablecer-password');

        Route::put('{id_usuario}/estado', [UsuarioController::class, 'alternarEstado'])
            ->middleware('auth.jwt:auth_usuario_alternar_estado')
            ->name('protected.auth.usuario.alternar-estado');

        Route::put('{id_usuario}/2fa/estado', [UsuarioController::class, 'alternar2FAEstado'])
            ->middleware('auth.jwt:auth_usuario_alternar_2fa')
            ->name('protected.auth.usuario.2fa-estado');
    });

    Route::prefix('api-key')->group(function () {
        Route::get('/', [ApiKeyController::class, 'index'])
            ->middleware('auth.jwt:auth_api_key_index')
            ->name('protected.auth.api-key.index');

        Route::post('/', [ApiKeyController::class, 'guardar'])
            ->middleware('auth.jwt:auth_api_key_guardar')
            ->name('protected.auth.api-key.guardar');

        Route::put('{api_key}/estado', [ApiKeyController::class, 'estado'])
            ->middleware('auth.jwt:auth_api_key_estado')
            ->name('protected.auth.api-key.estado');

        Route::put('{api_key}/permisos', [ApiKeyController::class, 'sincronizarPermisos'])
            ->middleware('auth.jwt:auth_api_key_permisos_sincronizar')
            ->name('protected.auth.api-key.permisos-sincronizar');
    });
});
