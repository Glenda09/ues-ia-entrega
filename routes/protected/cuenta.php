<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cuenta\CuentaController;

Route::prefix('cuenta')->group(function () {
    Route::get('/', [CuentaController::class, 'info'])
        ->middleware('auth.jwt')
        ->name('protected.cuenta.info');

    Route::put('/2fa', [CuentaController::class, 'alternar2FAEstado'])
        ->middleware('auth.jwt')
        ->name('protected.cuenta.2fa');

    Route::post('/2fa/setup', [CuentaController::class, 'iniciarConfiguracion2FA'])
        ->middleware('auth.jwt')
        ->name('protected.cuenta.2fa-setup');
});
