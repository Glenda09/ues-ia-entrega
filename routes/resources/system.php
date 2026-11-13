<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\System\LogsController;

Route::get('/ping', function () {
    return response()->json([
        'message' => 'pong',
    ]);
});

Route::get('/request-logs', [LogsController::class, 'consultarRequestLogs'])
    ->middleware([
        'auth.apikey:resources_request_logs',
        'log.request:data'
    ])
    ->name('resources.system.logs.request');

Route::get('/system-logs', [LogsController::class, 'consultarSystemLogs'])
    ->middleware([
        'auth.apikey:resources_system_logs',
        'log.request:data'
    ])
    ->name('resources.system.logs.system');

Route::get('/jobs', [LogsController::class, 'consultarJobs'])
    ->middleware([
        'auth.apikey:resources_jobs',
        'log.request:data'
    ])
    ->name('resources.system.logs.jobs');
