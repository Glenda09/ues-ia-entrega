<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Model binding de recursos académicos (rutas /api/academico).
        $bindings = [
            'carrera' => \App\Models\Academico\Carrera::class,
            'docente' => \App\Models\Academico\Docente::class,
            'pensum' => \App\Models\Academico\Pensum::class,
            'ciclo' => \App\Models\Academico\CicloCarrera::class,
            'oferta' => \App\Models\Academico\OfertaMateria::class,
            'grupo' => \App\Models\Academico\Grupo::class,
            'convocatoria' => \App\Models\Academico\ConvocatoriaIngreso::class,
            'solicitud' => \App\Models\Academico\SolicitudIngreso::class,
            'expediente' => \App\Models\Academico\Expediente::class,
            'inscripcion' => \App\Models\Academico\Inscripcion::class,
            'documento' => \App\Models\Academico\DocumentoExpediente::class,
        ];
        foreach ($bindings as $parametro => $modelo) {
            \Illuminate\Support\Facades\Route::pattern($parametro, '[0-9]+');
            \Illuminate\Support\Facades\Route::model($parametro, $modelo);
        }

        // Rate limit para API: 60 peticiones por minuto por usuario o IP
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();
            return Limit::perMinute((int) config('app.api_rate_limit', 60))
                ->by($key)
                ->response(function (Request $_, array $headers) {
                    return response()->json([
                        'message' => 'Hemos detectado demasiadas peticiones, espera un momento antes de volver a intentar',
                    ], 429, $headers);
                });
        });

        // Regla de validación personalizada: sanitize_html
        // Acepta solo etiquetas HTML incluidas en la lista permitida; rechaza el resto.
        // Uso: 'campo' => 'sanitize_html' o 'campo' => 'sanitize_html:b,strong,a'
        Validator::extend('sanitize_html', function ($attribute, $value, $parameters, $validator) {
            $isAllowed = function ($val) use ($parameters) {
                // Lista por defecto de etiquetas permitidas
                $defaultAllowed = [
                    'b','strong','i','em','u','s','a','p','br','ul','ol','li','span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
                ];

                $allowed = $parameters && count($parameters) > 0
                    ? array_values(array_filter(array_map(fn ($t) => strtolower(trim($t)), $parameters)))
                    : $defaultAllowed;

                $checkString = function (string $html) use ($allowed) {
                    // Encuentra todas las etiquetas y valida que pertenezcan al allowlist
                    if (!preg_match_all('#<\s*\/??\s*([a-z0-9]+)\b[^>]*>#i', $html, $m)) {
                        return true; // no hay etiquetas
                    }
                    $tags = array_unique(array_map('strtolower', $m[1]));
                    foreach ($tags as $tag) {
                        if (!in_array($tag, $allowed, true)) {
                            return false;
                        }
                    }
                    return true;
                };

                if (is_string($val)) {
                    return $checkString($val);
                }
                if (is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v) && !$checkString($v)) {
                            return false;
                        }
                    }
                    return true;
                }
                return true; // otros tipos: no contienen HTML
            };

            return $isAllowed($value);
        });

        // Crea el campo y la relación del campo "creado_por" a través del comando $table->creadoPor()
        Blueprint::macro('creadoPor', function ($nullable = false, $tableName = 'auth_usuario', $columnName = 'id_usuario') {
            $column = $this->uuid('creado_por')->comment('Usuario que creó el registro');

            if ($nullable) {
                $column->nullable();
            }

            $this->foreign('creado_por')->references($columnName)->on($tableName);

            return $column;
        });

        Blueprint::macro('estado', function ($nullable = false, $tableName = 'estado', $columnName = 'codigo') {
            $column = $this->string('id_estado')->default('ACTIVO')->comment('Estado del registro');

            if ($nullable) {
                $column->nullable();
            }

            $this->foreign('id_estado')->references($columnName)->on($tableName);

            return $column;
        });
    }
}
