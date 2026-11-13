<?php

namespace App\Services\Auth;

use App\Models\Auth\AuthUsuarioPerfil;
use App\Models\Seguridad\SeguridadPerfilAccion;
use App\Models\Seguridad\SeguridadPermiso;
use App\Models\Seguridad\SeguridadPermisoAccion;
use Illuminate\Support\Facades\Cache;

class PermisosEfectivosService
{
    public const CODIGO_PERFIL_SUPER_ADMIN = 'super_admin';

    public const PERMISO_ROOT = 'root';

    /**
     * Resuelve los permisos atómicos de un usuario: perfiles activos → acciones activas → permisos activos.
     *
     * @return array<int, string>
     */
    public function paraUsuario(string $idUsuario): array
    {
        $ttl = (int) config('auth.permisos_efectivos_ttl', 60);

        return Cache::remember($this->cacheKey($idUsuario), $ttl, function () use ($idUsuario) {
            return $this->resolverDesdeRoles($idUsuario);
        });
    }

    /**
     * Resuelve acciones activas asignadas por perfiles (sin asignación directa usuario-acción).
     *
     * @return array<int, string>
     */
    public function accionesParaUsuario(string $idUsuario): array
    {
        $codigosPerfil = $this->codigosPerfilActivos($idUsuario);
        if ($codigosPerfil === []) {
            return [];
        }

        return SeguridadPerfilAccion::query()
            ->whereIn('codigo_perfil', $codigosPerfil)
            ->whereHas('accion', function ($query) {
                $query->where('activo', true)
                    ->whereHas('grupo', fn ($grupo) => $grupo->where('activo', true));
            })
            ->pluck('id_accion')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function invalidarUsuario(string $idUsuario): void
    {
        Cache::forget($this->cacheKey($idUsuario));
    }

    public function invalidarTodos(): void
    {
        if (! Cache::has('rbac:permisos:version')) {
            Cache::forever('rbac:permisos:version', 2);

            return;
        }

        Cache::increment('rbac:permisos:version');
    }

    /**
     * @return array<int, string>
     */
    protected function resolverDesdeRoles(string $idUsuario): array
    {
        $codigosPerfil = $this->codigosPerfilActivos($idUsuario);
        if ($codigosPerfil === []) {
            return [];
        }

        if (in_array(self::CODIGO_PERFIL_SUPER_ADMIN, $codigosPerfil, true)) {
            $permisos = SeguridadPermiso::query()
                ->where('activo', true)
                ->pluck('permiso')
                ->map(fn ($permiso) => (string) $permiso)
                ->all();

            return array_values(array_unique(array_merge([self::PERMISO_ROOT], $permisos)));
        }

        $acciones = $this->accionesParaUsuario($idUsuario);
        if ($acciones === []) {
            return [];
        }

        return SeguridadPermisoAccion::query()
            ->whereIn('id_accion', $acciones)
            ->whereHas('permiso', fn ($query) => $query->where('activo', true))
            ->pluck('permiso')
            ->map(fn ($permiso) => (string) $permiso)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function codigosPerfilActivos(string $idUsuario): array
    {
        return AuthUsuarioPerfil::query()
            ->where('id_usuario', $idUsuario)
            ->whereHas('perfil', fn ($query) => $query->where('activo', true))
            ->pluck('codigo_perfil')
            ->map(fn ($codigo) => (string) $codigo)
            ->unique()
            ->values()
            ->all();
    }

    protected function cacheKey(string $idUsuario): string
    {
        $version = (int) Cache::get('rbac:permisos:version', 1);

        return 'rbac:permisos:v'.$version.':'.$idUsuario;
    }
}
