<?php

use Illuminate\Support\Facades\Route;

/*
| Rutas públicas académicas bajo /api/publico (nombres public.academico.*).
*/
Route::prefix('publico')->name('public.academico.')->group(function () {
    foreach (glob(base_path('routes/public/academico/*.php')) as $archivo) {
        require $archivo;
    }
});
