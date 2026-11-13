<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\RequestLoggerMiddleware;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\ApiKeyMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        using: function () {
            Route::group([], base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware(['api', 'auth.jwt', 'throttle:api'])
                ->group(base_path('routes/protected.php'));

            Route::prefix('api')
                ->middleware(['api', 'throttle:api'])
                ->group(base_path('routes/public.php'));

            Route::prefix('resource')
                ->middleware(['api', 'auth.apikey', 'throttle:api'])
                ->group(base_path('routes/resources.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'log.request' => RequestLoggerMiddleware::class,
            'auth.jwt' => JwtMiddleware::class,
            'auth.apikey' => ApiKeyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Fuerza respuestas JSON para rutas API aunque el cliente no envíe Accept: application/json
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*');
        });

        // Errores de dominio académico: { message, code, meta? } con estado estable.
        $exceptions->render(function (\App\Support\Academico\DominioException $e) {
            return response()->json($e->cuerpo(), $e->estado);
        });

        // Registro ausente o fuera de alcance en rutas académicas: 404 sin revelar existencia.
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/academico/*') || $request->is('api/publico/*')) {
                return response()->json(['message' => 'Registro no encontrado.', 'code' => 'NO_ENCONTRADO'], 404);
            }
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if (($request->is('api/academico/*') || $request->is('api/publico/*')) && $e->getPrevious() instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json(['message' => 'Registro no encontrado.', 'code' => 'NO_ENCONTRADO'], 404);
            }
        });

        // Reintento de deadlock agotado.
        $exceptions->render(function (\Illuminate\Database\DeadlockException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Conflicto de concurrencia, reintenta la operación.', 'code' => 'CONCURRENCIA_REINTENTAR'], 409);
            }
        });
    })->create();
