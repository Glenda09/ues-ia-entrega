<?php

use Illuminate\Support\Facades\Route;

/*
| Rutas académicas autenticadas bajo /api/academico (nombres protected.academico.*).
| Cada módulo registra su archivo en routes/protected/academico/.
*/
Route::prefix('academico')->name('protected.academico.')->group(function () {
    foreach (glob(base_path('routes/protected/academico/*.php')) as $archivo) {
        require $archivo;
    }
});
