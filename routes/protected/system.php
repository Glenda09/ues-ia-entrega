<?php

use Illuminate\Support\Facades\Route;

use App\Jobs\QueueTestJob;
use App\Http\Controllers\System\LogsController;

Route::get('/queue-test', function () {
    QueueTestJob::dispatch();

    return response()->json([
        'message' => 'Job de prueba encolado (' . date('d-m-Y H:i:s') . ')',
    ]);
})->middleware(['auth.jwt:root'])->name('protected.system.queue');

Route::get('/request-logs', [LogsController::class, 'consultarRequestLogs'])
    ->middleware(['auth.jwt:resources_request_logs'])
    ->name('protected.system.logs.request');

Route::get('/system-logs', [LogsController::class, 'consultarSystemLogs'])
    ->middleware(['auth.jwt:resources_system_logs'])
    ->name('protected.system.logs.system');

Route::get('/jobs', [LogsController::class, 'consultarJobs'])
    ->middleware(['auth.jwt:resources_jobs'])
    ->name('protected.system.logs.jobs');
