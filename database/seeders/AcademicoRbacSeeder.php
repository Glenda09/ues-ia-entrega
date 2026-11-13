<?php

namespace Database\Seeders;

use App\Models\Academico\IaTarifa;
use App\Models\App\AppModulo;
use App\Models\Seguridad\SeguridadAccion;
use App\Models\Seguridad\SeguridadGrupoAccion;
use App\Models\Seguridad\SeguridadMenuOpcion;
use App\Models\Seguridad\SeguridadMenuOpcionPermiso;
use App\Models\Seguridad\SeguridadPerfil;
use App\Models\Seguridad\SeguridadPerfilAccion;
use App\Models\Seguridad\SeguridadPermiso;
use App\Models\Seguridad\SeguridadPermisoAccion;
use App\Services\Auth\PermisosEfectivosService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * BASE-01: registra el módulo académico, perfiles funcionales, acciones y permisos.
 *
 * Idempotente: puede ejecutarse varias veces sin duplicar ni retirar asignaciones
 * existentes de usuarios a perfiles. Se ejecuta en cada `db:seed` (no se marca en `seeders`).
 */
class AcademicoRbacSeeder extends Seeder
{
    /**
     * Sincroniza el menú académico. Los padres desplegables (ruta nula) se identifican por nombre
     * para no fusionarse entre sí; los hijos por (módulo, ruta).
     */
    private function sincronizarMenu(array $opciones): void
    {
        foreach ($opciones as $op) {
            $hijos = $op['childrens'] ?? [];
            $permisos = $op['permisos'] ?? [];
            unset($op['childrens'], $op['permisos']);
            $padre = SeguridadMenuOpcion::query()->where('id_modulo', $op['id_modulo'])
                ->when($op['ruta'] === null, fn ($q) => $q->whereNull('ruta')->where('nombre', $op['nombre']), fn ($q) => $q->where('ruta', $op['ruta']))
                ->first() ?? new SeguridadMenuOpcion(['id_opcion' => (string) Str::uuid()]);
            $padre->fill($op)->save();
            $this->permisosMenu($padre->id_opcion, $permisos);
            foreach ($hijos as $h) {
                $ph = $h['permisos'] ?? [];
                unset($h['permisos']);
                $hijo = SeguridadMenuOpcion::query()->where('id_modulo', $h['id_modulo'])->where('ruta', $h['ruta'])->first()
                    ?? new SeguridadMenuOpcion(['id_opcion' => (string) Str::uuid()]);
                $hijo->fill($h + ['id_opcion_padre' => $padre->id_opcion])->save();
                $this->permisosMenu($hijo->id_opcion, $ph);
            }
        }
    }

    private function permisosMenu(string $idOpcion, array $permisos): void
    {
        SeguridadMenuOpcionPermiso::query()->where('id_opcion', $idOpcion)->whereNotIn('id_permiso', $permisos)->delete();
        foreach ($permisos as $p) {
            SeguridadMenuOpcionPermiso::query()->firstOrCreate(['id_opcion' => $idOpcion, 'id_permiso' => $p], ['creado_por' => DatabaseSeeder::ID_USUARIO_ADMINISTRADOR]);
        }
    }

    public function run(): void
    {
        $catalogo = include __DIR__.'/data/academico/permisos.php';

        AppModulo::updateOrCreate(['codigo' => 'ACADEMICO'], ['nombre' => 'Académico', 'id_estado' => 'ACTIVO']);

        $grupo = $catalogo['grupo'];
        SeguridadGrupoAccion::updateOrCreate(['id_grupo' => $grupo['id_grupo']], [
            'nombre' => $grupo['nombre'],
            'descripcion' => $grupo['descripcion'],
            'activo' => true,
        ]);

        foreach ($catalogo['perfiles'] as $codigo => [$nombre, $descripcion]) {
            SeguridadPerfil::updateOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'activo' => true,
                'creado_por' => DatabaseSeeder::ID_USUARIO_ADMINISTRADOR,
            ]);
        }

        foreach ($catalogo['acciones'] as $accion) {
            $registro = SeguridadAccion::updateOrCreate(
                ['id_grupo' => $grupo['id_grupo'], 'accion' => $accion['accion']],
                ['descripcion' => $accion['descripcion'], 'activo' => true]
            );

            foreach ($accion['permisos'] as $permiso => $descripcion) {
                SeguridadPermiso::updateOrCreate(['permiso' => $permiso], ['descripcion' => $descripcion, 'activo' => true]);
                SeguridadPermisoAccion::updateOrCreate(['permiso' => $permiso, 'id_accion' => $registro->id_accion], []);
            }

            foreach ($accion['perfiles'] as $perfil) {
                SeguridadPerfilAccion::updateOrCreate(
                    ['codigo_perfil' => $perfil, 'id_accion' => $registro->id_accion],
                    ['creado_por' => DatabaseSeeder::ID_USUARIO_ADMINISTRADOR]
                );
            }
        }

        $this->sincronizarMenu(include __DIR__.'/data/academico/menu.php');

        // IA-BASE-01: tarifas iniciales (USD por millón de tokens). Editables desde /academico/configuracion.
        $tarifas = [
            ['proveedor' => 'mock', 'modelo' => (string) config('ia.modelo', 'mock-academico-1'), 'precio_entrada_mtok' => config('ia.tarifa_simulada.entrada'), 'precio_salida_mtok' => config('ia.tarifa_simulada.salida'), 'fuente' => 'Tarifa sintética del adaptador simulado'],
            ['proveedor' => 'mock', 'modelo' => 'mock-academico-1', 'precio_entrada_mtok' => config('ia.tarifa_simulada.entrada'), 'precio_salida_mtok' => config('ia.tarifa_simulada.salida'), 'fuente' => 'Tarifa sintética del adaptador simulado'],
            ['proveedor' => 'openai', 'modelo' => 'gpt-6-luna', 'precio_entrada_mtok' => '0.10', 'precio_salida_mtok' => '0.50', 'fuente' => 'https://developers.openai.com/api/docs/pricing (verificar antes de habilitar)'],
        ];
        foreach ($tarifas as $t) {
            if (! IaTarifa::query()->where('proveedor', $t['proveedor'])->where('modelo', $t['modelo'])->exists()) {
                IaTarifa::create($t + ['version' => 1, 'precio_entrada_cache_mtok' => 0, 'vigente_desde' => '2020-01-01 00:00:00']);
            }
        }

        app(PermisosEfectivosService::class)->invalidarTodos();
    }
}
