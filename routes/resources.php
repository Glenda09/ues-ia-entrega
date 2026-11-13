<?php

use Illuminate\Support\Facades\Route;

require base_path('routes/resources/system.php');

Route::prefix('/storage')->group(function () {
    require base_path('routes/resources/storage.php');
});
