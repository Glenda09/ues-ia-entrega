<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/')->group(function () {
    require base_path('routes/public/example.php');
});

Route::prefix('/auth')->group(function () {
    require base_path('routes/public/auth.php');
});

require base_path('routes/public/personalizacion.php');
require base_path('routes/public/academico.php');
