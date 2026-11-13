<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\Seguridad\SeguridadAccion;
use App\Models\Seguridad\SeguridadPermiso;
use App\Models\Seguridad\SeguridadGrupoAccion;
use App\Models\Seguridad\SeguridadPermisoAccion;
use App\Models\Seguridad\SeguridadPerfil;
use App\Models\Seguridad\SeguridadPerfilAccion;

class PermisosAccionesSeeder extends Seeder
{
    const ID_PERMISO_ROOT = 'root';
    const ID_GRUPO_ACCIONES_PRINCIPAL = 'administracion';
    const ID_ACCION_PRINCIPAL = 'fbde5fd0-df99-4cbc-9e14-f601b714163a';
    const ID_USUARIO_ADMINISTRADOR = '537b8b56-341c-4322-869a-de00d11cf10e';
    const ID_PERFIL_ADMINISTRADOR = 'super_admin';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grupoAcciones = include __DIR__ . '/data/permisos_acciones.php';

        // Recorre los grupos de acciones y crea/actualiza relaciones idempotentes
        foreach ($grupoAcciones as $grupo) {
            SeguridadGrupoAccion::updateOrCreate(
                ['id_grupo' => $grupo['id_grupo']],
                [
                    'nombre' => $grupo['nombre'],
                    'descripcion' => $grupo['descripcion'],
                    'activo' => $grupo['activo'],
                ]
            );

            $idGrupoAccionesSeguridad = $grupo['id_grupo'];

            // Acciones del grupo
            foreach ($grupo['acciones'] as $accion) {
                if (!empty($accion['id_accion'])) {
                    $seguridadAccion = SeguridadAccion::updateOrCreate(
                        ['id_accion' => $accion['id_accion']],
                        [
                            'id_grupo' => $idGrupoAccionesSeguridad,
                            'accion' => $accion['accion'],
                            'descripcion' => $accion['descripcion'],
                            'activo' => $accion['activo'],
                        ]
                    );
                } else {
                    // Usar combinación natural si no se provee id
                    $seguridadAccion = SeguridadAccion::updateOrCreate(
                        [
                            'id_grupo' => $idGrupoAccionesSeguridad,
                            'accion' => $accion['accion'],
                        ],
                        [
                            'descripcion' => $accion['descripcion'],
                            'activo' => $accion['activo'],
                        ]
                    );
                }

                // Permisos de la acción
                foreach ($accion['permisos'] as $permiso) {
                    SeguridadPermiso::updateOrCreate(
                        ['permiso' => $permiso['permiso']],
                        [
                            'descripcion' => $permiso['descripcion'],
                            'activo' => $permiso['activo'],
                        ]
                    );

                    // Relación permiso-acción
                    SeguridadPermisoAccion::updateOrCreate(
                        [
                            'permiso' => $permiso['permiso'],
                            'id_accion' => $seguridadAccion->id_accion,
                        ],
                        []
                    );
                }
            }
        }

        // Perfil Super Administrador
        SeguridadPerfil::updateOrCreate(
            ['codigo' => self::ID_PERFIL_ADMINISTRADOR],
            [
                'nombre' => 'Super Administrador',
                'descripcion' => 'Perfil con acceso completo a todas las funcionalidades del sistema',
                'activo' => true,
                'creado_por' => self::ID_USUARIO_ADMINISTRADOR,
            ]
        );

        // Perfil Usuario
        SeguridadPerfil::updateOrCreate(
            ['codigo' => 'user'],
            [
                'nombre' => 'Usuario',
                'descripcion' => 'Perfil con acceso limitado a funcionalidades específicas del sistema',
                'activo' => true,
                'creado_por' => self::ID_USUARIO_ADMINISTRADOR,
            ]
        );

        SeguridadPerfilAccion::updateOrCreate(
            [
                'codigo_perfil' => self::ID_PERFIL_ADMINISTRADOR,
                'id_accion' => self::ID_ACCION_PRINCIPAL,
            ],
            [
                'creado_por' => self::ID_USUARIO_ADMINISTRADOR,
            ]
        );
    }
}
