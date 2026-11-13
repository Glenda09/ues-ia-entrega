<?php

use App\Tasks\TestTask;
use App\Tasks\PurgeLogsTask;
use Illuminate\Support\Facades\Schedule;

/*
* Todas las tareas usan onOneServer(): con varios contenedores worker (o el rol "all"
* en más de un host) solo uno ejecuta cada tarea, mediante el lock de la caché compartida.
*
* ->withoutOverlapping(5) // Previene ejecuciones concurrentes por 5 minutos
* ->onOneServer(); // Solo ejecuta en un servidor en un entorno de múltiples servidores
*/

Schedule::call(function () {
    $handler = new TestTask();
    $handler->execute();
})
    ->name('schedules.health')
    ->dailyAt('01:00')
    ->onOneServer();

Schedule::call(function () {
    $handler = new PurgeLogsTask();
    $handler->execute();
})
    ->name('schedules.logs.purge')
    ->dailyAt('02:00')
    ->onOneServer();

// EXP-01: limpieza diaria de cargas temporales sin referencia (> 24 h).
Schedule::call(fn () => app(\App\Services\Academico\AdmisionService::class)->limpiarHuerfanos())
    ->name('schedules.academico.limpiar-anexos')
    ->dailyAt('03:00')
    ->withoutOverlapping(10)
    ->onOneServer();

// IA-BASE-01: reconciliador de reservas inciertas (cada minuto; liquida conservadoramente tras 24 h).
Schedule::call(fn () => app(\App\Services\IA\PresupuestoIAService::class)->reconciliar())
    ->name('schedules.ia.reconciliar')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();
