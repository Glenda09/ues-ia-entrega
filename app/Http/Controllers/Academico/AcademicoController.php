<?php

namespace App\Http\Controllers\Academico;

use App\Http\Controllers\Controller;
use App\Support\Academico\Actor;
use App\Support\Academico\DominioException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Base de controladores académicos: actor JWT, validación estricta, paginación y respuestas.
 */
abstract class AcademicoController extends Controller
{
    protected function actor(Request $request): Actor
    {
        $usuario = $request->attributes->get('auth_user');
        if (! $usuario) {
            throw new DominioException('NO_AUTENTICADO', 'Sesión no válida.', 401);
        }

        return new Actor((string) $usuario->id_usuario, (array) $request->attributes->get('auth_permissions', []));
    }

    /**
     * Valida y rechaza campos desconocidos con 422 (contratos comunes).
     *
     * @param  array<string, mixed>  $reglas
     * @param  array<int, string>  $permitidosExtra  Claves de nivel superior adicionales permitidas (p. ej. `page`).
     */
    protected function validarEstricto(Request $request, array $reglas, array $permitidosExtra = []): array
    {
        $permitidos = array_unique(array_merge(
            array_map(fn ($k) => explode('.', $k)[0], array_keys($reglas)),
            $permitidosExtra
        ));
        $desconocidos = array_diff(array_keys($request->all()), $permitidos);
        if ($desconocidos !== []) {
            throw ValidationException::withMessages(collect($desconocidos)->mapWithKeys(fn ($k) => [$k => ['Campo no permitido.']])->all());
        }

        return $request->validate($reglas);
    }

    /**
     * Parámetros de paginación: página 1, 20 filas, máximo 100.
     *
     * @return array{0:int,1:int}
     */
    protected function paginacion(Request $request): array
    {
        $datos = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return [(int) ($datos['page'] ?? 1), (int) ($datos['per_page'] ?? 20)];
    }

    /**
     * Respuesta paginada con `meta` estándar.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $consulta
     */
    protected function paginado(Request $request, $consulta, callable $mapa, string $mensaje = 'Consulta realizada'): JsonResponse
    {
        [$pagina, $porPagina] = $this->paginacion($request);
        $p = $consulta->paginate($porPagina, ['*'], 'page', $pagina);

        return response()->json([
            'message' => $mensaje,
            'data' => collect($p->items())->map($mapa)->values(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'last_page' => $p->lastPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    protected function ok(mixed $data, string $mensaje = 'Consulta realizada', array $meta = []): JsonResponse
    {
        $cuerpo = ['message' => $mensaje, 'data' => $data];
        if ($meta !== []) {
            $cuerpo['meta'] = $meta;
        }

        return response()->json($cuerpo);
    }

    protected function creado(mixed $data, string $mensaje = 'Registro creado'): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'data' => $data], 201);
    }
}
