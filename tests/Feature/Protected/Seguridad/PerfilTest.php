<?php

namespace Tests\Feature\Protected\Seguridad;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use App\Models\Seguridad\SeguridadPerfil;
use App\Models\Seguridad\SeguridadAccion;
use App\Models\Seguridad\SeguridadGrupoAccion;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    private string $grupoId = 'seguridad';

    protected function setUp(): void
    {
        parent::setUp();
        SeguridadGrupoAccion::firstOrCreate([
            'id_grupo' => $this->grupoId,
        ], [
            'nombre' => 'Seguridad',
            'descripcion' => 'Grupo',
            'activo' => true,
        ]);
    }

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

    public function test_index_lists_profiles(): void
    {
        $creator = AuthUsuario::create([
            'username' => 'creator_idx',
            'email' => 'creator_idx@example.com',
            'password' => Hash::make('password123'),
            'id_estado' => 'ACTIVO',
        ]);

        SeguridadPerfil::insert([
            ['codigo' => 'perfil_a', 'nombre' => 'Perfil A', 'descripcion' => 'A', 'activo' => true, 'creado_por' => $creator->id_usuario, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'perfil_b', 'nombre' => 'Perfil B', 'descripcion' => 'B', 'activo' => false, 'creado_por' => $creator->id_usuario, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $res = $this->withHeaders($this->authHeaders())
            ->getJson(route('protected.seguridad.perfil.index'))
            ->assertStatus(200)
            ->json('data');

        $this->assertGreaterThanOrEqual(2, count($res));
    }

    public function test_store_creates_profile_with_actions(): void
    {
        $accion1 = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'A1',
            'activo' => true,
        ]);

        $accion2 = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'A2',
            'activo' => true,
        ]);

        $payload = [
            'codigo' => 'operador',
            'nombre' => 'Operador',
            'descripcion' => 'Operaciones',
            'activo' => true,
            'acciones' => [$accion1->id_accion, $accion2->id_accion],
        ];

        $this->withHeaders($this->authHeaders())
            ->postJson(route('protected.seguridad.perfil.guardar'), $payload)
            ->assertStatus(201)
            ->assertJsonFragment(['codigo' => 'operador']);

        $this->assertDatabaseHas('seguridad_perfil', ['codigo' => 'operador']);
        $this->assertDatabaseHas('seguridad_perfil_accion', ['codigo_perfil' => 'operador', 'id_accion' => $accion1->id_accion]);
    }

    public function test_show_returns_profile(): void
    {
        $creator1 = AuthUsuario::create([
            'username' => 'creator_show',
            'email' => 'creator_show@example.com',
            'password' => Hash::make('password123'),
            'id_estado' => 'ACTIVO',
        ]);

        SeguridadPerfil::create([
            'codigo' => 'visor',
            'nombre' => 'Visor',
            'creado_por' => $creator1->id_usuario,
            'activo' => true,
        ]);

        $this->withHeaders($this->authHeaders())
            ->getJson(route('protected.seguridad.perfil.mostrar', 'visor'))
            ->assertStatus(200)
            ->assertJsonFragment(['codigo' => 'visor']);
    }

    public function test_update_syncs_actions(): void
    {
        $creator2 = AuthUsuario::create([
            'username' => 'creator_update',
            'email' => 'creator_update@example.com',
            'password' => Hash::make('password123'),
            'id_estado' => 'ACTIVO',
        ]);
        $perfil = SeguridadPerfil::create([
            'codigo' => 'editor',
            'nombre' => 'Editor',
            'creado_por' => $creator2->id_usuario,
            'activo' => true,
        ]);
        $a1 = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'Editar',
            'activo' => true,
        ]);
        $a2 = SeguridadAccion::create([
            'id_accion' => Str::uuid()->toString(),
            'id_grupo' => $this->grupoId,
            'accion' => 'Publicar',
            'activo' => true,
        ]);

        $creator = AuthUsuario::create([
            'username' => 'creator_user',
            'email' => 'creator@example.com',
            'password' => Hash::make('password123'),
            'id_estado' => 'ACTIVO',
        ]);
        $perfil->acciones()->attach($a1->id_accion, [
            'creado_por' => $creator->id_usuario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders())
            ->putJson(route('protected.seguridad.perfil.actualizar', 'editor'), [
                'nombre' => 'Editor Senior',
                'acciones' => [$a2->id_accion],
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['nombre' => 'Editor Senior']);

        $this->assertDatabaseMissing('seguridad_perfil_accion', ['codigo_perfil' => 'editor', 'id_accion' => $a1->id_accion]);
        $this->assertDatabaseHas('seguridad_perfil_accion', ['codigo_perfil' => 'editor', 'id_accion' => $a2->id_accion]);
    }

    public function test_destroy_deletes_profile(): void
    {
        $creator3 = AuthUsuario::create([
            'username' => 'creator_destroy',
            'email' => 'creator_destroy@example.com',
            'password' => Hash::make('password123'),
            'id_estado' => 'ACTIVO',
        ]);
        SeguridadPerfil::create([
            'codigo' => 'temp',
            'nombre' => 'Temporal',
            'creado_por' => $creator3->id_usuario,
            'activo' => true,
        ]);

        $this->withHeaders($this->authHeaders())
            ->deleteJson(route('protected.seguridad.perfil.eliminar', 'temp'))
            ->assertStatus(200)
            ->assertJson(['message' => 'Perfil eliminado']);

        $this->assertDatabaseMissing('seguridad_perfil', ['codigo' => 'temp']);
    }
}
