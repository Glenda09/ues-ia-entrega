<?php

use App\Http\Controllers\Personalizacion\PersonalizacionController;
use Illuminate\Support\Facades\Route;

Route::get('public/personalizacion', [PersonalizacionController::class, 'mostrar'])->name('public.personalizacion.mostrar');
Route::get('public/personalizacion/assets/{slot}/{version}', [PersonalizacionController::class, 'imagen'])
    ->name('public.personalizacion.asset');
