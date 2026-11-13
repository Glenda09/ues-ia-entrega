<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\SeguridadAccion;
use App\Services\Auth\PermisosEfectivosService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AccionController extends Controller
{
    public function __construct(private PermisosEfectivosService $permisosEfectivosService) {}

    public function index(Request $request)
    {
        $ttl = (int) config('catalog.cache_ttl');
        $cacheKey = 'catalog:seguridad_accion:index';

        // Filtros opcionales
        $idGrupo = $request->get('id_grupo');
        $accionSearch = $request->get('accion');

        $hasFilters = filled($idGrupo) || filled($accionSearch);

        if ($hasFilters) {
            $query = SeguridadAccion::with(['grupo']);

            if ($idGrupo) {
                $query->where('id_grupo', $idGrupo);
            }

            if ($accionSearch) {
                // Búsqueda case-insensitive: usa ILIKE en PostgreSQL; fallback portable para otros drivers
                if (DB::getDriverName() === 'pgsql') {
                    $query->where('accion', 'ilike', "%{$accionSearch}%");
                } else {
                    $needle = mb_strtolower($accionSearch, 'UTF-8');
                    $query->whereRaw('LOWER(accion) LIKE ?', ["%{$needle}%"]);
                }
            }

            $acciones = $query->get();
        } else {
            // Solo cacheamos el listado completo sin filtros
            $acciones = Cache::remember($cacheKey, $ttl, function () {
                return SeguridadAccion::with(['grupo'])->get();
            });
        }

        return response()->json(['data' => $acciones]);
    }

    public function guardar(Request $request)
    {
        $data = $request->validate([
            'id_grupo' => ['required', 'string', 'exists:seguridad_grupo_accion,id_grupo'],
            'accion' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'activo' => ['sometimes', 'boolean'],
            'permisos' => ['sometimes', 'array', 'min:1'],
            'permisos.*' => ['string', 'exists:seguridad_permiso,permiso'],
        ]);
        $permisos = $data['permisos'] ?? [];
        unset($data['permisos']);

        DB::beginTransaction();
        try {
            $accion = SeguridadAccion::create($data);

            if (! empty($permisos)) {
                $accion->permisosAsociados()->syncWithoutDetaching($permisos);
            }

            $accion->load(['grupo']);
            Cache::forget('catalog:seguridad_accion:index');
            $this->permisosEfectivosService->invalidarTodos();
            DB::commit();

            return response()->json(['data' => $accion], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function mostrar(string $id_accion)
    {
        try {
            $accion = SeguridadAccion::with(['grupo', 'permisosAsociados'])->findOrFail($id_accion);

            return response()->json(['data' => $accion]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Acción no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    public function actualizar(Request $request, string $id_accion)
    {
        try {
            $accion = SeguridadAccion::findOrFail($id_accion);
            $validated = $request->validate([
                'id_grupo' => ['sometimes', 'string', 'exists:seguridad_grupo_accion,id_grupo'],
                'accion' => ['sometimes', 'string', 'max:255'],
                'descripcion' => ['nullable', 'string', 'max:500'],
                'activo' => ['sometimes', 'boolean'],
            ]);
            $accion->update($validated);
            $accion->load(['grupo']);
            Cache::forget('catalog:seguridad_accion:index');
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['data' => $accion]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Acción no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    public function eliminar(string $id_accion)
    {
        try {
            $accion = SeguridadAccion::findOrFail($id_accion);

            return DB::transaction(function () use ($accion) {
                $accion->permisosAsociados()->detach();
                try {
                    $accion->delete();
                } catch (\Illuminate\Database\QueryException $e) {
                    return response()->json([
                        'message' => 'No se puede eliminar la acción porque está en uso',
                    ], Response::HTTP_CONFLICT);
                }
                Cache::forget('catalog:seguridad_accion:index');
                $this->permisosEfectivosService->invalidarTodos();

                return response()->noContent();
            });
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Acción no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    public function alternarEstado(string $id_accion)
    {
        try {
            $accion = SeguridadAccion::findOrFail($id_accion);
            $accion->activo = ! (bool) $accion->activo;
            $accion->save();
            Cache::forget('catalog:seguridad_accion:index');
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['message' => 'Estado de la acción actualizado correctamente']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Acción no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    public function asignarPermisos(Request $request, string $id_accion)
    {
        try {
            $accion = SeguridadAccion::findOrFail($id_accion);
            $validated = $request->validate([
                'permisos' => ['required', 'array', 'min:1'],
                'permisos.*' => ['required', 'string', 'max:255'],
            ]);
            $permisos = $validated['permisos'];

            $accion->permisosAsociados()->syncWithoutDetaching($permisos);
            $accion->load('permisosAsociados');
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['data' => $accion]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Acción no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    public function sincronizarPermisos(Request $request, string $id_accion)
    {
        try {
            $accion = SeguridadAccion::findOrFail($id_accion);
            $validated = $request->validate([
                'permisos' => ['required', 'array', 'min:1'],
                'permisos.*' => ['required', 'string', 'max:255'],
            ]);
            $permisos = $validated['permisos'];

            $accion->permisosAsociados()->sync($permisos);
            $accion->load('permisosAsociados');
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['data' => $accion]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Acción no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    public function removerPermisos(Request $request, string $id_accion)
    {
        try {
            $accion = SeguridadAccion::findOrFail($id_accion);
            $validated = $request->validate([
                'permisos' => ['required', 'array', 'min:1'],
                'permisos.*' => ['required', 'string', 'max:255'],
            ]);
            $permisos = $validated['permisos'];
            $accion->permisosAsociados()->detach($permisos);
            $accion->load('permisosAsociados');
            $this->permisosEfectivosService->invalidarTodos();

            return response()->json(['data' => $accion]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Acción no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }
}
