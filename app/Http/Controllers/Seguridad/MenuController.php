<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Database\Seeders\DatabaseSeeder;

use App\Models\Seguridad\SeguridadMenuOpcion;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $ttl = (int) config('catalog.cache_ttl');
        $version = (int) Cache::get('catalog:seguridad_menu:version', 1);
        $cacheKey = 'catalog:seguridad_menu:v' . $version . ':index:' . md5(json_encode([
            'id_modulo' => $request->get('id_modulo'),
            'search' => $request->get('search'),
        ]));

        $payload = Cache::remember($cacheKey, $ttl, function () use ($request) {
            $search = trim((string) $request->get('search', ''));
            $like = $search !== '' ? "%{$search}%" : null;

            $query = SeguridadMenuOpcion::query();
            $query->with(['hijos' => function ($childQuery) use ($like) {
                $this->applyMenuOrdering($childQuery);

                if ($like !== null) {
                    $childQuery->where('ruta', 'ilike', $like);
                }
            }]);
            $query->whereNull('id_opcion_padre');

            if ($idModulo = $request->get('id_modulo')) {
                $query->where('id_modulo', $idModulo);
            }

            if ($like !== null) {
                $query->where(function ($q) use ($like) {
                    $q->where('ruta', 'like', $like)
                        ->orWhereHas('hijos', function ($childQuery) use ($like) {
                            $childQuery->where('ruta', 'ilike', $like);
                        });
                });
            }

            $this->applyMenuOrdering($query);
            $items = $query->get();

            if ($like !== null) {
                $needle = mb_strtolower($search);
                $items->each(function (SeguridadMenuOpcion $menu) use ($needle) {
                    $filtered = $menu->hijos->filter(function (SeguridadMenuOpcion $child) use ($needle) {
                        $haystack = mb_strtolower((string) ($child->ruta ?? ''));
                        return $needle === '' || mb_strpos($haystack, $needle) !== false;
                    })->values();

                    $menu->setRelation('hijos', $filtered);
                });
            }

            return [
                'data' => $items->map(fn($m) => $this->toResource($m, true))->values()->all(),
            ];
        });

        return response()->json($payload);
    }

    public function listarPadres()
    {
        $ttl = (int) config('catalog.cache_ttl');
        $version = (int) Cache::get('catalog:seguridad_menu:version', 1);
        $cacheKey = 'catalog:seguridad_menu:v' . $version . ':index:' . 'padres';

        $payload = Cache::remember($cacheKey, $ttl, function () {
            $query = SeguridadMenuOpcion::query();
            $query->where('desplegable', true);
            $items = $query->get();

            return $items->map(fn($m) => $this->toResource($m))->values()->all();
        });

        return response()->json($payload);
    }

    public function guardar(Request $request)
    {
        $data = $request->validate([
            'id_modulo' => ['required', 'string', 'exists:app_modulo,codigo'],
            'id_opcion_padre' => ['nullable', 'uuid', 'exists:seguridad_menu_opcion,id_opcion'],
            'nombre' => ['required', 'string', 'max:150'],
            'ruta' => ['nullable', 'string', 'max:255'],
            'icono' => ['nullable', 'string', 'max:100'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'id_estado' => ['sometimes', 'string', 'exists:estado,codigo'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['string', 'exists:seguridad_permiso,permiso'],
        ]);

        $permisos = $data['permisos'] ?? [];
        unset($data['permisos']);

        $menu = SeguridadMenuOpcion::create($data);

        if (!empty($permisos)) {
            $creadoPor = (string) ($request->user()->id_usuario ?? DatabaseSeeder::ID_USUARIO_ADMINISTRADOR);
            $attach = array_fill_keys($permisos, ['creado_por' => $creadoPor]);
            $menu->permisos()->attach($attach);
        }

        $menu->load('permisos');
        // Invalida caches de listado de menú
        $this->bumpIndexCacheVersion();
        return response()->json($this->toResource($menu, false, true), Response::HTTP_CREATED);
    }

    public function mostrar(string $id_opcion)
    {
        try {
            $menu = SeguridadMenuOpcion::with('permisos')->findOrFail($id_opcion);
            return response()->json($this->toResource($menu, true, true));
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Opción de menú no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    public function actualizar(Request $request, string $id_opcion)
    {
        try {
            $menu = SeguridadMenuOpcion::findOrFail($id_opcion);

            $data = $request->validate([
                'id_modulo' => ['sometimes', 'required', 'string', 'exists:app_modulo,codigo'],
                'id_opcion_padre' => ['nullable', 'uuid', 'exists:seguridad_menu_opcion,id_opcion'],
                'nombre' => ['sometimes', 'required', 'string', 'max:150'],
                'ruta' => ['nullable', 'string', 'max:255'],
                'icono' => ['nullable', 'string', 'max:100'],
                'orden' => ['nullable', 'integer', 'min:0'],
                'id_estado' => ['sometimes', 'string', 'exists:estado,codigo'],
                'permisos' => ['sometimes', 'array'],
                'permisos.*' => ['string', 'exists:seguridad_permiso,permiso'],
            ]);

            $permisos = $data['permisos'] ?? null;
            unset($data['permisos']);

            $menu->fill($data);
            $menu->save();

            if (is_array($permisos)) {
                $creadoPor = (string) ($request->user()->id_usuario ?? DatabaseSeeder::ID_USUARIO_ADMINISTRADOR);
                $menu->permisos()->sync([]);
                $attach = array_fill_keys($permisos, ['creado_por' => $creadoPor]);
                if (!empty($attach)) {
                    $menu->permisos()->attach($attach);
                }
            }

            $menu->load('permisos');
            // Invalida caches de listado de menú
            $this->bumpIndexCacheVersion();
            return response()->json($this->toResource($menu, false, true));
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Opción de menú no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    public function eliminar(string $id_opcion)
    {
        try {
            $menu = SeguridadMenuOpcion::findOrFail($id_opcion);
            $menu->delete();
            // Invalida caches de listado de menú
            $this->bumpIndexCacheVersion();
            return response()->json(['message' => 'Opción de menú eliminada']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Opción de menú no encontrada'], Response::HTTP_NOT_FOUND);
        }
    }

    private function toResource(SeguridadMenuOpcion $m, bool $withChildrens = false, bool $withRelations = false): array
    {
        $payload = [
            'id_opcion' => $m->id_opcion,
            'id_modulo' => $m->id_modulo,
            'id_opcion_padre' => $m->id_opcion_padre,
            'padre' => $m->padre ? [
                'nombre' => $m->padre->nombre,
                'ruta' => $m->padre->ruta,
                'icono' => $m->padre->icono,
            ] : null,
            'nombre' => $m->nombre,
            'ruta' => $m->ruta,
            'icono' => $m->icono,
            'orden' => (int) $m->orden,
            'id_estado' => $m->id_estado,
            'desplegable' => $m->desplegable
        ];

        if ($withChildrens) {
            $payload['childrens'] = $m->hijos->values()->all();
        }

        if ($withRelations) {
            $payload['permisos'] = $m->relationLoaded('permisos')
                ? $m->permisos->pluck('permiso')->values()->all()
                : $m->permisos()->pluck('seguridad_permiso.permiso')->values()->all();
        }

        return $payload;
    }

    private function applyMenuOrdering(Builder|Relation $query): void
    {
        $query->orderBy('orden')->orderBy('id_modulo');
    }

    private function bumpIndexCacheVersion(): void
    {
        // Incrementa la versión usada en las keys del índice para invalidar todas las variantes cacheadas
        $incremented = Cache::increment('catalog:seguridad_menu:version');
        if ($incremented === false) {
            Cache::forever('catalog:seguridad_menu:version', 2);
        }
    }
}
