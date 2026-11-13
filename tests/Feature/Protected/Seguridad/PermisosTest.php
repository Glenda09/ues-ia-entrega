<?php

namespace Tests\Feature\Protected\Seguridad;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use Illuminate\Support\Facades\Hash;
use App\Models\Seguridad\SeguridadPermiso;

class PermisosTest extends TestCase
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

    public function test_index_lists_permissions(): void
    {
        SeguridadPermiso::insert([
            ['permiso' => 'usuario_leer', 'descripcion' => 'Leer', 'activo' => true],
            ['permiso' => 'usuario_escribir', 'descripcion' => 'Escribir', 'activo' => false],
        ]);

        $res = $this->withHeaders($this->authHeaders())
            ->getJson(route('protected.seguridad.permisos.index'))
            ->assertStatus(200)
            ->json('data');

        $this->assertGreaterThanOrEqual(2, count($res));
    }

    public function test_store_creates_permission(): void
    {
        $payload = [
            'permiso' => 'usuario_crear',
            'descripcion' => 'Crear usuarios',
            'activo' => true,
        ];

        $this->withHeaders($this->authHeaders())
            ->postJson(route('protected.seguridad.permisos.guardar'), $payload)
            ->assertStatus(201)
            ->assertJsonFragment(['permiso' => 'usuario_crear']);

        $this->assertDatabaseHas('seguridad_permiso', ['permiso' => 'usuario_crear']);
    }

    public function test_show_returns_permission(): void
    {
        SeguridadPermiso::create(['permiso' => 'usuario_leer', 'descripcion' => 'Leer', 'activo' => true]);

        $this->withHeaders($this->authHeaders())
            ->getJson(route('protected.seguridad.permisos.mostrar', 'usuario_leer'))
            ->assertStatus(200)
            ->assertJsonFragment(['permiso' => 'usuario_leer']);
    }

    public function test_update_modifies_permission(): void
    {
        SeguridadPermiso::create(['permiso' => 'usuario_actualizar', 'descripcion' => 'Act desc', 'activo' => true]);

        $this->withHeaders($this->authHeaders())
            ->putJson(route('protected.seguridad.permisos.actualizar', 'usuario_actualizar'), [
                'descripcion' => 'Nueva desc',
                'activo' => false,
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['descripcion' => 'Nueva desc', 'activo' => false]);
    }

    public function test_destroy_deletes_permission(): void
    {
        SeguridadPermiso::create(['permiso' => 'usuario_eliminar', 'descripcion' => 'Del', 'activo' => true]);

        $this->withHeaders($this->authHeaders())
            ->deleteJson(route('protected.seguridad.permisos.eliminar', 'usuario_eliminar'))
            ->assertStatus(200)
            ->assertJson(['message' => 'Permiso eliminado']);

        $this->assertDatabaseMissing('seguridad_permiso', ['permiso' => 'usuario_eliminar']);
    }
}
