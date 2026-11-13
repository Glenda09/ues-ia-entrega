<?php

namespace Tests\Feature\Protected\Seguridad;

use App\Models\App\Estado;
use App\Models\Auth\AuthApiKey;
use App\Models\Auth\AuthApiKeyPermiso;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use App\Models\Seguridad\SeguridadAccion;
use App\Models\Seguridad\SeguridadGrupoAccion;
use App\Models\Seguridad\SeguridadPerfil;
use App\Models\Seguridad\SeguridadPerfilAccion;
use App\Models\Seguridad\SeguridadPermiso;
use App\Models\Seguridad\SeguridadPermisoAccion;
use App\Services\Auth\PermisosEfectivosService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RbacTest extends TestCase
{
    private string $grupoId = 'rbac_pruebas';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        SeguridadGrupoAccion::firstOrCreate(
            ['id_grupo' => $this->grupoId],
            [
                'nombre' => 'RBAC pruebas',
                'descripcion' => 'Grupo para pruebas de autorización',
                'activo' => true,
            ]
        );
    }

    public function test_usuario_sin_rol_recibe_403(): void
    {
        $headers = $this->loginHeaders($this->crearUsuario());

        $this->withHeaders($headers)
            ->getJson(route('protected.seguridad.perfil.index'))
            ->assertStatus(403);
    }

    public function test_accion_otorga_grupo_de_permisos_y_niega_el_resto(): void
    {
        $usuario = $this->crearUsuario();
        $accion = $this->crearAccion('Administrar perfiles de prueba', [
            'seguridad_perfil_index',
            'seguridad_perfil_mostrar',
        ]);
        $this->asignarPerfil($usuario, 'visor_perfiles', [$accion->id_accion]);
        $headers = $this->loginHeaders($usuario);

        $this->withHeaders($headers)
            ->getJson(route('protected.seguridad.perfil.index'))
            ->assertStatus(200);

        $this->withHeaders($headers)
            ->getJson(route('protected.auth.usuario.index'))
            ->assertStatus(403);
    }

    public function test_union_de_dos_roles_es_aditiva(): void
    {
        $usuario = $this->crearUsuario();
        $accionPerfiles = $this->crearAccion('Paquete perfiles', ['seguridad_perfil_index']);
        $accionUsuarios = $this->crearAccion('Paquete usuarios', ['auth_usuario_index']);

        $this->asignarPerfil($usuario, 'rol_perfiles', [$accionPerfiles->id_accion]);
        $this->asignarPerfil($usuario, 'rol_usuarios', [$accionUsuarios->id_accion]);
        $headers = $this->loginHeaders($usuario);

        $this->withHeaders($headers)
            ->getJson(route('protected.seguridad.perfil.index'))
            ->assertStatus(200);

        $this->withHeaders($headers)
            ->getJson(route('protected.auth.usuario.index'))
            ->assertStatus(200);
    }

    public function test_perfil_inactivo_no_otorga_permisos(): void
    {
        $usuario = $this->crearUsuario();
        $accion = $this->crearAccion('Paquete inactivo', ['seguridad_perfil_index']);
        $perfil = $this->asignarPerfil($usuario, 'rol_inactivo', [$accion->id_accion]);
        $perfil->activo = false;
        $perfil->save();

        $this->withHeaders($this->loginHeaders($usuario))
            ->getJson(route('protected.seguridad.perfil.index'))
            ->assertStatus(403);
    }

    public function test_super_admin_accede_a_todo(): void
    {
        $usuario = $this->crearUsuario();
        AuthUsuarioPerfil::create([
            'id_usuario' => $usuario->id_usuario,
            'codigo_perfil' => 'super_admin',
            'creado_por' => $usuario->id_usuario,
        ]);

        $this->withHeaders($this->loginHeaders($usuario))
            ->getJson(route('protected.seguridad.perfil.index'))
            ->assertStatus(200);

        $this->withHeaders($this->loginHeaders($usuario))
            ->getJson(route('protected.auth.usuario.index'))
            ->assertStatus(200);
    }

    public function test_cambiar_permisos_de_accion_invalida_cache(): void
    {
        $usuario = $this->crearUsuario();
        $accion = $this->crearAccion('Paquete cache', ['seguridad_perfil_index']);
        $this->asignarPerfil($usuario, 'rol_cache', [$accion->id_accion]);
        $headers = $this->loginHeaders($usuario);

        $this->withHeaders($headers)
            ->getJson(route('protected.seguridad.perfil.index'))
            ->assertStatus(200);

        $accion->permisosAsociados()->sync([]);
        app(PermisosEfectivosService::class)->invalidarTodos();

        $this->withHeaders($headers)
            ->getJson(route('protected.seguridad.perfil.index'))
            ->assertStatus(403);
    }

    public function test_api_key_autoriza_por_permisos_directos(): void
    {
        $apiKeyValue = (string) Str::uuid();
        AuthApiKey::create([
            'cliente' => 'RBAC_TEST',
            'api_key' => $apiKeyValue,
            'token_hash' => hash('sha256', $apiKeyValue),
            'id_estado' => Estado::ACTIVO,
        ]);
        SeguridadPermiso::firstOrCreate(
            ['permiso' => 'resources_request_logs'],
            ['descripcion' => 'Logs de peticiones', 'activo' => true]
        );
        AuthApiKeyPermiso::create([
            'api_key' => $apiKeyValue,
            'id_permiso' => 'resources_request_logs',
        ]);

        $this->withHeaders([
            'X-API-Key' => $apiKeyValue,
            'Accept' => 'application/json',
        ])->getJson(route('resources.system.logs.request'))
            ->assertStatus(200);
    }

    public function test_no_existe_asignacion_directa_usuario_accion(): void
    {
        $this->assertFalse(Schema::hasTable('auth_usuario_accion'));
    }

    public function test_actualizar_usuario_valida_perfiles_existentes(): void
    {
        $admin = AuthUsuario::where('email', 'admin@example.com')->firstOrFail();
        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => $admin->email,
            'password' => 'password123',
        ])->assertStatus(200);

        $usuario = $this->crearUsuario();

        $this->withHeaders(['Authorization' => 'Bearer '.$login->json('access_token')])
            ->putJson(route('protected.auth.usuario.actualizar', $usuario->id_usuario), [
                'perfiles' => ['perfil_inexistente'],
            ])
            ->assertStatus(422);
    }

    private function crearUsuario(): AuthUsuario
    {
        return AuthUsuario::factory()->create([
            'password' => 'password123',
            'id_estado' => 'ACTIVO',
        ]);
    }

    /**
     * @param  array<int, string>  $permisos
     */
    private function crearAccion(string $nombre, array $permisos): SeguridadAccion
    {
        $accion = SeguridadAccion::create([
            'id_accion' => (string) Str::uuid(),
            'id_grupo' => $this->grupoId,
            'accion' => $nombre,
            'descripcion' => $nombre,
            'activo' => true,
        ]);

        foreach ($permisos as $permiso) {
            SeguridadPermiso::firstOrCreate(
                ['permiso' => $permiso],
                ['descripcion' => $permiso, 'activo' => true]
            );
            SeguridadPermisoAccion::firstOrCreate([
                'permiso' => $permiso,
                'id_accion' => $accion->id_accion,
            ]);
        }

        return $accion;
    }

    /**
     * @param  array<int, string>  $acciones
     */
    private function asignarPerfil(AuthUsuario $usuario, string $codigo, array $acciones): SeguridadPerfil
    {
        $perfil = SeguridadPerfil::firstOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => $codigo,
                'descripcion' => $codigo,
                'activo' => true,
                'creado_por' => $usuario->id_usuario,
            ]
        );

        foreach ($acciones as $idAccion) {
            $pivot = new SeguridadPerfilAccion;
            $pivot->codigo_perfil = $perfil->codigo;
            $pivot->id_accion = $idAccion;
            $pivot->creado_por = $usuario->id_usuario;
            $pivot->save();
        }

        AuthUsuarioPerfil::firstOrCreate(
            [
                'id_usuario' => $usuario->id_usuario,
                'codigo_perfil' => $perfil->codigo,
            ],
            ['creado_por' => $usuario->id_usuario]
        );

        return $perfil;
    }

    /**
     * @return array<string, string>
     */
    private function loginHeaders(AuthUsuario $usuario): array
    {
        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => $usuario->email,
            'password' => 'password123',
        ])->assertStatus(200);

        return ['Authorization' => 'Bearer '.$login->json('access_token')];
    }
}
