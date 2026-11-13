<?php

namespace Tests\Feature\Protected\Seguridad;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use Illuminate\Support\Facades\Hash;
use App\Models\Seguridad\SeguridadGrupoAccion;

class GrupoAccionTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        $usuario = AuthUsuario::create([
            'username' => 'user_test',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'id_estado' => 'ACTIVO',
        ]);

        AuthUsuarioPerfil::create([
            'id_usuario' => $usuario->id_usuario,
            'codigo_perfil' => 'super_admin',
            'creado_por' => $usuario->id_usuario,
        ]);

        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => 'user@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        return ['Authorization' => 'Bearer ' . $login->json('access_token')];
    }

    public function test_index_lists_groups(): void
    {
        SeguridadGrupoAccion::insert([
            ['id_grupo' => 'grupo_a', 'nombre' => 'Grupo A', 'descripcion' => 'Admin', 'activo' => true],
            ['id_grupo' => 'grupo_b', 'nombre' => 'Grupo B', 'descripcion' => 'Seg', 'activo' => false],
        ]);

        $res = $this->withHeaders($this->authHeaders())
            ->getJson(route('protected.seguridad.grupo-accion.index'))
            ->assertStatus(200)
            ->json('data');

        $this->assertGreaterThanOrEqual(2, count($res));
    }

    public function test_store_creates_group(): void
    {
        $payload = [
            'id_grupo' => 'configuracion',
            'nombre' => 'Configuración',
            'descripcion' => 'Config del sistema',
            'activo' => true,
        ];

        $this->withHeaders($this->authHeaders())
            ->postJson(route('protected.seguridad.grupo-accion.guardar'), $payload)
            ->assertStatus(201)
            ->assertJsonFragment(['id_grupo' => 'configuracion']);

        $this->assertDatabaseHas('seguridad_grupo_accion', ['id_grupo' => 'configuracion']);
    }

    public function test_show_returns_group(): void
    {
        SeguridadGrupoAccion::create(['id_grupo' => 'operaciones', 'nombre' => 'Operaciones', 'descripcion' => 'Ops', 'activo' => true]);

        $this->withHeaders($this->authHeaders())
            ->getJson(route('protected.seguridad.grupo-accion.mostrar', 'operaciones'))
            ->assertStatus(200)
            ->assertJsonFragment(['id_grupo' => 'operaciones']);
    }

    public function test_update_modifies_group(): void
    {
        SeguridadGrupoAccion::create(['id_grupo' => 'reportes', 'nombre' => 'Reportes', 'descripcion' => 'Rep', 'activo' => true]);

        $this->withHeaders($this->authHeaders())
            ->putJson(route('protected.seguridad.grupo-accion.actualizar', 'reportes'), [
                'nombre' => 'Reportería',
                'descripcion' => 'Nueva desc',
                'activo' => false,
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['nombre' => 'Reportería', 'activo' => false]);
    }

    public function test_destroy_deletes_group(): void
    {
        SeguridadGrupoAccion::create(['id_grupo' => 'temporal', 'nombre' => 'Temporal', 'descripcion' => 'Temp', 'activo' => true]);

        $this->withHeaders($this->authHeaders())
            ->deleteJson(route('protected.seguridad.grupo-accion.eliminar', 'temporal'))
            ->assertStatus(200)
            ->assertJson(['message' => 'Grupo de acciones eliminado']);

        $this->assertDatabaseMissing('seguridad_grupo_accion', ['id_grupo' => 'temporal']);
    }

    public function test_toggle_state_switches_flag(): void
    {
        SeguridadGrupoAccion::create(['id_grupo' => 'aprobadores', 'nombre' => 'Aprobadores', 'descripcion' => 'Grupo de aprobadores', 'activo' => false]);

        $this->withHeaders($this->authHeaders())
            ->putJson(route('protected.seguridad.grupo-accion.alternar-estado', 'aprobadores'))
            ->assertStatus(200)
            ->assertJson(['message' => 'Estado del grupo actualizado correctamente']);

        $this->assertDatabaseHas('seguridad_grupo_accion', ['id_grupo' => 'aprobadores', 'activo' => true]);
    }
}
