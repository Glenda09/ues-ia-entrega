<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\SeguridadPerfil;
use App\Services\Auth\PermisosEfectivosService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PerfilController extends Controller
{
    public function __construct(private PermisosEfectivosService $permisosEfectivosService) {}

    public function index(Request $request)
    {
        $ttl = (int) config('catalog.cache_ttl');
        $version = (int) Cache::get('catalog:seguridad_perfil:version', 1);
        $cacheKey = 'catalog:seguridad_perfil:v'.$version.':index:'.md5(json_encode([
            'solo_activos' => $request->boolean('solo_activos'),
            'search' => $request->get('search'),
            'per_page' => (int) ($request->get('per_page', 10)),
            'page' => (int) ($request->get('page', 1)),
        ]));

        $payload = Cache::remember($cacheKey, $ttl, function () use ($request) {
            $query = SeguridadPerfil::query();

            if ($request->boolean('solo_activos')) {
                $query->where('activo', true);
            }

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('codigo', 'like', "%$search%")
                        ->orWhere('nombre', 'like', "%$search%")
                        ->orWhere('descripcion', 'like', "%$search%");
                });
            }

            $perPage = (int) ($request->get('per_page', 10));
            $items = $query->orderBy('codigo')->paginate($perPage);

            return [
                'data' => $items->getCollection()->map(fn ($p) => $this->toResource($p))->values()->all(),
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
            'codigo' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:seguridad_perfil,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'acciones' => ['required', 'array', 'min:1'],
            'acciones.*' => ['string', 'exists:seguridad_accion,id_accion'],
        ]);

        $acciones = $data['acciones'];
        unset($data['acciones']);

        DB::beginTransaction();
        try {
            $creadoPor = optional($request->attributes->get('auth_user'))->id_usuario;
            $data['creado_por'] = $creadoPor;
            $perfil = SeguridadPerfil::create($data);

            $now = now();
            $pivotData = [];
            foreach ($acciones as $idAccion) {
                $pivotData[$idAccion] = [
                    'creado_por' => $creadoPor,
                    'created_at' => $now,
                ];
            }
            $perfil->acciones()->attach($pivotData);

            $perfil->load('acciones');
            $response = response()->json($this->toResource($perfil, true), Response::HTTP_CREATED);
            DB::commit();
            // Invalida caches de listado de perfiles
            $this->bumpIndexCacheVersion();
            $this->permisosEfectivosService->invalidarTodos();

            return $response;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function mostrar(string $codigo)
    {
        try {
            $perfil = SeguridadPerfil::with('acciones')->findOrFail($codigo);

            return response()->json($this->toResource($perfil, true));
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    public function actualizar(Request $request, string $codigo)
    {
        try {
            $perfil = SeguridadPerfil::findOrFail($codigo);
            $data = $request->validate([
                'nombre' => ['sometimes', 'required', 'string', 'max:150'],
                'descripcion' => ['nullable', 'string', 'max:255'],
                'activo' => ['sometimes', 'boolean'],
                'acciones' => ['required', 'array', 'min:1'],
                'acciones.*' => ['string', 'exists:seguridad_accion,id_accion'],
            ]);

            DB::beginTransaction();
            $acciones = $data['acciones'];
            unset($data['acciones']);

            if (! empty($data)) {
                $perfil->fill($data);
                $perfil->save();
            }

            $now = now();
            $creadoPor = optional($request->attributes->get('auth_user'))->id_usuario;
            $syncData = [];
            foreach ($acciones as $idAccion) {
                $syncData[$idAccion] = [
                    'creado_por' => $creadoPor,
                    'created_at' => $now,
                ];
            }
            $perfil->acciones()->sync($syncData);

            $perfil->load('acciones');
            $response = response()->json($this->toResource($perfil, true));
            DB::commit();
            // Invalida caches de listado de perfiles
            $this->bumpIndexCacheVersion();
            $this->permisosEfectivosService->invalidarTodos();

            return $response;
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function eliminar(string $codigo)
    {
        try {
            $perfil = SeguridadPerfil::findOrFail($codigo);

            DB::beginTransaction();
            try {
                $perfil->acciones()->detach();
                try {
                    $perfil->delete();
                } catch (QueryException $e) {
                    DB::rollBack();

                    return response()->json([
                        'message' => 'No se puede eliminar el perfil porque está en uso',
                    ], Response::HTTP_CONFLICT);
                }
                DB::commit();
                // Invalida caches de listado de perfiles
                $this->bumpIndexCacheVersion();
                $this->permisosEfectivosService->invalidarTodos();

                return response()->json(['message' => 'Perfil eliminado']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    public function alternarEstado(string $codigo)
    {
        try {
            $perfil = SeguridadPerfil::findOrFail($codigo);

            $perfil->activo = ! (bool) $perfil->activo;
            $perfil->save();

            // Invalida caches de listado de perfiles
            $this->bumpIndexCacheVersion();
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['message' => 'Estado de perfil actualizado correctamente']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }
    }

    private function bumpIndexCacheVersion(): void
    {
        // Incrementa la versión usada en las keys del índice para invalidar todas las variantes cacheadas
        $incremented = Cache::increment('catalog:seguridad_perfil:version');
        if ($incremented === false) {
            Cache::forever('catalog:seguridad_perfil:version', 2);
        }
    }

    private function toResource(SeguridadPerfil $perfil, bool $withRelations = false): array
    {
        $base = [
            'codigo' => $perfil->codigo,
            'nombre' => $perfil->nombre,
            'descripcion' => $perfil->descripcion,
            'activo' => (bool) $perfil->activo,
        ];

        if ($withRelations) {
            $base['acciones'] = $perfil->acciones->map(fn ($a) => [
                'id_accion' => $a->id_accion,
                'id_grupo' => $a->id_grupo,
                'accion' => $a->accion,
                'descripcion' => $a->descripcion,
                'activo' => (bool) $a->activo,
            ])->values()->all();
        }

        return $base;
    }
}
