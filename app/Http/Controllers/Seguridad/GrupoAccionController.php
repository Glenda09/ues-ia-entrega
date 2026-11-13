<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\SeguridadGrupoAccion;
use App\Services\Auth\PermisosEfectivosService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class GrupoAccionController extends Controller
{
    private const INDEX_CACHE_PREFIX = 'catalog:seguridad_grupo_accion:index:';

    private const INDEX_CACHE_KEYS = 'catalog:seguridad_grupo_accion:index:keys';

    public function __construct(private PermisosEfectivosService $permisosEfectivosService) {}

    public function index(Request $request)
    {
        $ttl = (int) config('catalog.cache_ttl');
        $cacheKey = self::INDEX_CACHE_PREFIX.md5(json_encode([
            'solo_activos' => $request->boolean('solo_activos'),
            'search' => $request->get('search'),
            'per_page' => (int) ($request->get('per_page', 10)),
            'page' => (int) ($request->get('page', 1)),
        ]));

        $this->rememberIndexCacheKey($cacheKey);

        $payload = Cache::remember($cacheKey, $ttl, function () use ($request) {
            $query = SeguridadGrupoAccion::query();

            if ($request->boolean('solo_activos')) {
                $query->activos();
            }

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('id_grupo', 'like', "%$search%")
                        ->orWhere('nombre', 'like', "%$search%")
                        ->orWhere('descripcion', 'like', "%$search%");
                });
            }

            $perPage = (int) ($request->get('per_page', 10));
            $items = $query->orderBy('id_grupo')->paginate($perPage);

            return [
                'data' => $items->getCollection()->map(fn ($g) => $this->toResource($g))->values()->all(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ];
        });

        return response()->json($payload);
    }

    public function guardar(Request $request)
    {
        $data = $request->validate([
            'id_grupo' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:seguridad_grupo_accion,id_grupo'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $grupo = SeguridadGrupoAccion::create($data);
        $this->clearIndexCache();

        return response()->json($this->toResource($grupo), Response::HTTP_CREATED);
    }

    public function mostrar(string $id_grupo)
    {
        try {
            $grupo = SeguridadGrupoAccion::findOrFail($id_grupo);

            return response()->json($this->toResource($grupo));
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Grupo de acciones no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    public function actualizar(Request $request, string $id_grupo)
    {
        try {
            $grupo = SeguridadGrupoAccion::findOrFail($id_grupo);

            $data = $request->validate([
                'nombre' => ['sometimes', 'required', 'string', 'max:150'],
                'descripcion' => ['nullable', 'string', 'max:255'],
                'activo' => ['sometimes', 'boolean'],
            ]);

            $grupo->fill($data);
            $grupo->save();
            $this->clearIndexCache();
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json($this->toResource($grupo));
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Grupo de acciones no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    public function eliminar(string $id_grupo)
    {
        try {
            $grupo = SeguridadGrupoAccion::findOrFail($id_grupo);
            $grupo->delete();
            $this->clearIndexCache();
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['message' => 'Grupo de acciones eliminado']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Grupo de acciones no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    public function alternarEstado(string $id_grupo)
    {
        try {
            $grupo = SeguridadGrupoAccion::findOrFail($id_grupo);
            $grupo->activo = ! (bool) $grupo->activo;
            $grupo->save();
            $this->clearIndexCache();
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['message' => 'Estado del grupo actualizado correctamente']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Grupo de acciones no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    private function toResource(SeguridadGrupoAccion $g): array
    {
        return [
            'id_grupo' => $g->id_grupo,
            'nombre' => $g->nombre,
            'descripcion' => $g->descripcion,
            'activo' => (bool) $g->activo,
        ];
    }

    private function rememberIndexCacheKey(string $cacheKey): void
    {
        $keys = Cache::get(self::INDEX_CACHE_KEYS, []);

        if (! in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::forever(self::INDEX_CACHE_KEYS, $keys);
        }
    }

    private function clearIndexCache(): void
    {
        $keys = Cache::pull(self::INDEX_CACHE_KEYS, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
