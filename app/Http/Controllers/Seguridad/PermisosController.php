<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\SeguridadPermiso;
use App\Services\Auth\PermisosEfectivosService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PermisosController extends Controller
{
    public function __construct(private PermisosEfectivosService $permisosEfectivosService) {}

    public function index(Request $request)
    {
        $ttl = (int) config('catalog.cache_ttl');
        $version = (int) Cache::get('catalog:seguridad_permiso:version', 1);
        $cacheKey = 'catalog:seguridad_permiso:v'.$version.':index:'.md5(json_encode([
            'solo_activos' => $request->boolean('solo_activos'),
            'search' => $request->get('search'),
            'per_page' => (int) ($request->get('per_page', 10)),
            'page' => (int) ($request->get('page', 1)),
        ]));

        $payload = Cache::remember($cacheKey, $ttl, function () use ($request) {
            $query = SeguridadPermiso::query();

            if ($request->boolean('solo_activos')) {
                $query->activos();
            }

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('permiso', 'like', "%$search%")
                        ->orWhere('descripcion', 'like', "%$search%");
                });
            }

            $perPage = (int) ($request->get('per_page', 10));
            $permisos = $query->orderBy('permiso')->paginate($perPage);

            return [
                'data' => $permisos->getCollection()->map(fn ($p) => $this->toResource($p))->values()->all(),
                'meta' => [
                    'current_page' => $permisos->currentPage(),
                    'per_page' => $permisos->perPage(),
                    'total' => $permisos->total(),
                    'last_page' => $permisos->lastPage(),
                ],
            ];
        });

        return response()->json($payload);
    }

    public function guardar(Request $request)
    {
        $data = $request->validate([
            'permiso' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:seguridad_permiso,permiso'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $permiso = SeguridadPermiso::create($data);
        // Invalida caches de listado de permisos
        $this->bumpIndexCacheVersion();
        $this->permisosEfectivosService->invalidarTodos();

        return response()->json($this->toResource($permiso), Response::HTTP_CREATED);
    }

    public function mostrar(string $permiso)
    {
        try {
            $permisoModel = SeguridadPermiso::findOrFail($permiso);

            return response()->json($this->toResource($permisoModel));
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Permiso no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    public function actualizar(Request $request, string $permiso)
    {
        try {
            $permisoModel = SeguridadPermiso::findOrFail($permiso);
            $data = $request->validate([
                'descripcion' => ['nullable', 'string', 'max:255'],
                'activo' => ['sometimes', 'boolean'],
            ]);
            $permisoModel->fill($data);
            $permisoModel->save();
            // Invalida caches de listado de permisos
            $this->bumpIndexCacheVersion();
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json($this->toResource($permisoModel));
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Permiso no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    public function eliminar(string $permiso)
    {
        try {
            $permisoModel = SeguridadPermiso::findOrFail($permiso);
            $permisoModel->delete();
            // Invalida caches de listado de permisos
            $this->bumpIndexCacheVersion();
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['message' => 'Permiso eliminado']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Permiso no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    private function toResource(SeguridadPermiso $permiso): array
    {
        return [
            'permiso' => $permiso->permiso,
            'descripcion' => $permiso->descripcion,
            'activo' => (bool) $permiso->activo,
        ];
    }

    private function bumpIndexCacheVersion(): void
    {
        // Incrementa la versión usada en las keys del índice para invalidar todas las variantes cacheadas
        $incremented = Cache::increment('catalog:seguridad_permiso:version');
        if ($incremented === false) {
            Cache::forever('catalog:seguridad_permiso:version', 2);
        }
    }
}
