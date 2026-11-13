<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

Route::post('/login', [AuthController::class, 'login'])->name('public.auth.login');
Route::post('/refresh-token', [AuthController::class, 'refreshToken'])->name('public.auth.refresh-token');
Route::post('/request-password-recovery', [AuthController::class, 'requestPasswordRecovery'])->name('public.auth.request-password-recovery');
Route::post('/validate-recovery-token', [AuthController::class, 'validateRecoveryToken'])->name('public.auth.validate-recovery-token');
Route::post('/validate-2fa-token', [AuthController::class, 'validarToken2fa'])->name('public.auth.validate-2fa-token');
