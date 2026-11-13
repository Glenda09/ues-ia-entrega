<?php

namespace App\Services\Auth;

use App\Models\Auth\AuthUsuario;
use App\Models\Seguridad\SeguridadMenuOpcion;
use App\Models\Seguridad\SeguridadMenuOpcionPermiso;
use App\Services\Audit\SessionAuditRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class SesionUsuarioService
{
    public function __construct(
        private SessionAuditRepository $sessionAuditRepository,
        private PermisosEfectivosService $permisosEfectivosService,
    ) {}

    /**
     * Crea un registro de sesión en el backend de auditoría configurado con acciones y permisos del usuario.
     *
     * @param  string  $jwtToken  Token JWT emitido para el usuario
     */
    public function crearSesion(AuthUsuario $usuario, string $jwtToken, Request $request): void
    {
        try {
            $jti = $this->obtenerJti($jwtToken);

            // Calcular acciones y permisos desde perfiles y asignaciones directas
            [$acciones, $permisos] = $this->resolverAccionesYPermisos($usuario->id_usuario);

            // Construir menú autorizado a partir de permisos
            $menu = $this->construirMenuAutorizado($permisos);

            $this->sessionAuditRepository->create([
                'id_usuario' => $usuario->id_usuario,
                'jti' => $jti,
                'ip_address' => (string) $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'acciones' => array_values($acciones),
                'permisos' => array_values($permisos),
                'menu' => $menu,
            ]);
        } catch (\Throwable $e) {
            // No interrumpir el flujo de login si falla el guardado; loguear para diagnóstico
            Log::warning('No se pudo crear la sesión de usuario en la base de datos', [
                'id_usuario' => $usuario->id_usuario ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtiene el JTI del token. Si no existe, genera un hash como respaldo.
     */
    protected function obtenerJti(string $jwtToken): string
    {
        try {
            $payload = JWTAuth::setToken($jwtToken)->getPayload();
            $jti = $payload->get('jti');
            if (is_string($jti) && $jti !== '') {
                return $jti;
            }
        } catch (\Throwable $e) {
            // continuar a fallback
        }

        // Fallback estable si el claim no está presente
        return sha1($jwtToken);
    }

    /**
     * Resuelve acciones y permisos efectivos a partir de los roles del usuario.
     *
     * @return array{0: array<int|string>, 1: array<string>} [acciones, permisos]
     */
    public function resolverAccionesYPermisos(string $idUsuario): array
    {
        return [
            $this->permisosEfectivosService->accionesParaUsuario($idUsuario),
            $this->permisosEfectivosService->paraUsuario($idUsuario),
        ];
    }

    /**
     * Construye el menú autorizado para el usuario a partir de los permisos.
     *
     * - Busca en seguridad_menu_opcion_permiso por los permisos dados.
     * - Recupera las opciones de menú activas y sus padres necesarios.
     * - Organiza jerárquicamente por padre/hijos y orden ascendente.
     *
     * @param  array<int,string>  $permisos  Códigos de permiso autorizados para el usuario
     * @return array<int, array<string,mixed>> Estructura de menú serializable
     */
    public function construirMenuAutorizado(array $permisos): array
    {
        $opcionIds = $this->obtenerOpcionIdsPorPermisos($permisos);

        // Opciones que necesitan solo autenticación
        $otrasOpciones = $this->obtenerOpcionesSinPermisos();

        array_push($opcionIds, ...$otrasOpciones);

        $todosPorId = $this->cargarOpcionesYPadres($opcionIds);
        if ($todosPorId->isEmpty()) {
            return [];
        }

        $hijosPorPadre = $this->construirArbolOpciones($todosPorId);
        $this->ordenarOpcionesPorPadre($hijosPorPadre);

        return $this->mapearMenuSerializable($hijosPorPadre);
    }

    /**
     * Obtiene los IDs de opción autorizados por los permisos dados.
     *
     * @param  array<int, string>  $permisos
     * @return array<int, mixed>
     */
    private function obtenerOpcionIdsPorPermisos(array $permisos): array
    {
        $opcionIds = SeguridadMenuOpcionPermiso::query();
        if (! in_array('root', $permisos, true)) {
            $opcionIds->whereIn('id_permiso', $permisos);
        }

        return $opcionIds->pluck('id_opcion')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Obtiene los IDs de opción que solo requieren autenticación
     *
     * @return array<int, mixed>
     */
    private function obtenerOpcionesSinPermisos(): array
    {
        return SeguridadMenuOpcion::query()
            ->whereDoesntHave('permisos')
            ->pluck('id_opcion')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Carga todas las opciones activas y sus padres necesarios.
     *
     * @param  array<int, mixed>  $opcionIds
     * @return \Illuminate\Support\Collection
     */
    private function cargarOpcionesYPadres(array $opcionIds)
    {
        $opciones = SeguridadMenuOpcion::query()
            ->activos()
            ->whereIn('id_opcion', $opcionIds)
            ->get([
                'id_opcion', 'id_opcion_padre', 'nombre', 'ruta', 'icono', 'desplegable', 'orden',
            ]);

        if ($opciones->isEmpty()) {
            return collect();
        }

        $todosPorId = $opciones->keyBy('id_opcion');
        $pendientesPadre = $opciones->pluck('id_opcion_padre')
            ->filter()
            ->unique()
            ->values();

        while ($pendientesPadre->isNotEmpty()) {
            $faltantes = $pendientesPadre->diff($todosPorId->keys());
            if ($faltantes->isEmpty()) {
                break;
            }

            $padres = SeguridadMenuOpcion::query()
                ->activos()
                ->whereIn('id_opcion', $faltantes->all())
                ->get([
                    'id_opcion', 'id_opcion_padre', 'nombre', 'ruta', 'icono', 'desplegable', 'orden',
                ]);

            foreach ($padres as $padre) {
                $todosPorId->put($padre->id_opcion, $padre);
            }

            $pendientesPadre = $padres->pluck('id_opcion_padre')
                ->filter()
                ->unique()
                ->values();
        }

        return $todosPorId;
    }

    /**
     * Construye el árbol de opciones agrupando por id_opcion_padre.
     *
     * @param  \Illuminate\Support\Collection  $todosPorId
     * @return array<string, array>
     */
    private function construirArbolOpciones($todosPorId): array
    {
        $hijosPorPadre = [];
        foreach ($todosPorId as $opcion) {
            $padreId = $opcion->id_opcion_padre;
            $padreKey = $padreId ?: '_root_';
            if (! isset($hijosPorPadre[$padreKey])) {
                $hijosPorPadre[$padreKey] = [];
            }
            $hijosPorPadre[$padreKey][] = $opcion;
        }

        return $hijosPorPadre;
    }

    /**
     * Ordena las opciones hijas de cada padre por el campo 'orden' y luego por nombre.
     *
     * @param  array<string, array>  &$hijosPorPadre
     */
    private function ordenarOpcionesPorPadre(array &$hijosPorPadre): void
    {
        foreach ($hijosPorPadre as &$lista) {
            usort($lista, function ($a, $b) {
                $oa = (int) ($a->orden ?? 0);
                $ob = (int) ($b->orden ?? 0);
                if ($oa === $ob) {
                    return strcmp((string) $a->nombre, (string) $b->nombre);
                }

                return $oa <=> $ob;
            });
        }
        unset($lista);
    }

    /**
     * Mapea el árbol de opciones a una estructura serializable para el menú.
     *
     * @param  array<string, array>  $hijosPorPadre
     * @return array<int, array<string, mixed>>
     */
    private function mapearMenuSerializable(array $hijosPorPadre): array
    {
        $buildNode = function ($node) use (&$buildNode, &$hijosPorPadre) {
            $id = $node->id_opcion;
            $children = [];
            if (! empty($hijosPorPadre[$id])) {
                foreach ($hijosPorPadre[$id] as $child) {
                    $children[] = $buildNode($child);
                }
            }

            $item = [
                'title' => (string) $node->nombre,
                'icon' => $node->icono !== null ? (string) $node->icono : null,
            ];

            if (! empty($children)) {
                $item['children'] = $children;
            } else {
                if (! empty($node->ruta)) {
                    $item['path'] = (string) $node->ruta;
                }
            }

            return array_filter($item, fn ($v) => $v !== null);
        };

        $raices = $hijosPorPadre['_root_'] ?? [];
        $menu = [];
        foreach ($raices as $root) {
            $opcion = $buildNode($root);

            if (isset($opcion['children']) || isset($opcion['path'])) {
                $menu[] = $opcion;
            }
        }

        return $menu;
    }
}
