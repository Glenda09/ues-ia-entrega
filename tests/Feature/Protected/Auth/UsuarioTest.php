<?php

namespace Tests\Feature\Protected\Auth;

use App\Models\Auth\AuthUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;
use Carbon\Carbon;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        $admin = AuthUsuario::where('email', 'admin@example.com')->first();
        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => $admin->email,
            'password' => 'password123',
        ])->json();
        return ['Authorization' => 'Bearer ' . $login['access_token']];
    }

    public function test_index_usuarios(): void
    {
        AuthUsuario::factory()->count(3)->create(['id_estado' => 'ACTIVO']);
        $headers = $this->authHeaders();
        $resp = $this->withHeaders($headers)->getJson(route('protected.auth.usuario.index'));
        $resp->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_usuario',
                        'username',
                        'email',
                        'id_estado',
                        'estado' => [
                            'codigo',
                            'nombre_estado',
                            'label',
                            'color',
                            'icono',
                            'activo',
                        ],
                        'perfiles',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_usuario_index_filters_by_query(): void
    {
        $headers = $this->authHeaders();

        AuthUsuario::factory()->create([
            'username' => 'lara_user',
            'email' => 'lara@example.com',
            'id_estado' => 'ACTIVO',
        ]);
        AuthUsuario::factory()->create([
            'username' => 'otro',
            'email' => 'otro@example.com',
            'id_estado' => 'ACTIVO',
        ]);

        $resp = $this->withHeaders($headers)
            ->getJson(route('protected.auth.usuario.index', ['query' => 'lara']));

        $resp->assertStatus(200)
            ->assertJsonFragment(['email' => 'lara@example.com'])
            ->assertJsonPath('data.0.estado.codigo', 'ACTIVO');
    }

    public function test_usuario_index_filters_by_created_at_range(): void
    {
        $headers = $this->authHeaders();

        AuthUsuario::factory()->create([
            'username' => 'enero_user',
            'email' => 'enero@example.com',
            'id_estado' => 'ACTIVO',
            'created_at' => Carbon::create(2024, 1, 10, 12, 0, 0),
            'updated_at' => Carbon::create(2024, 1, 10, 12, 0, 0),
        ]);
        AuthUsuario::factory()->create([
            'username' => 'feb_user',
            'email' => 'feb@example.com',
            'id_estado' => 'ACTIVO',
            'created_at' => Carbon::create(2024, 2, 15, 10, 0, 0),
            'updated_at' => Carbon::create(2024, 2, 15, 10, 0, 0),
        ]);

        $resp = $this->withHeaders($headers)->getJson(
            route('protected.auth.usuario.index', [
                'created_at_desde' => '2024-02-01',
                'created_at_hasta' => '2024-02-28',
            ])
        );

        $resp->assertStatus(200)
            ->assertJsonFragment(['email' => 'feb@example.com']);
    }

    public function test_usuario_index_pagination_per_page(): void
    {
        $headers = $this->authHeaders();

        AuthUsuario::factory()->count(30)->create(['id_estado' => 'ACTIVO']);

        $resp = $this->withHeaders($headers)
            ->getJson(route('protected.auth.usuario.index', ['per_page' => 10]));

        $resp->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonCount(10, 'data');
    }

    public function test_guardar_usuario(): void
    {
        $headers = $this->authHeaders();
        $payload = [
            'username' => 'nuevo_usuario',
            'email' => 'nuevo_usuario@example.com',
            'perfiles' => ['super_admin']
        ];
        $resp = $this->withHeaders($headers)->postJson(route('protected.auth.usuario.guardar'), $payload);
        $resp->assertStatus(201)->assertJsonStructure(['message','data'=>['id_usuario']]);
        $this->assertDatabaseHas('auth_usuario', ['email' => 'nuevo_usuario@example.com']);
    }

    public function test_mostrar_usuario(): void
    {
        $headers = $this->authHeaders();
        $user = AuthUsuario::factory()->create(['username' => 'ver_usuario','email' => 'ver_usuario@example.com','id_estado' => 'ACTIVO']);
        $resp = $this->withHeaders($headers)->getJson(route('protected.auth.usuario.mostrar', $user->id_usuario));
        $resp->assertStatus(200)->assertJsonStructure(['id_usuario','username','email','id_estado']);
    }

    public function test_actualizar_usuario(): void
    {
        $headers = $this->authHeaders();
        $user = AuthUsuario::factory()->create(['username' => 'to_update','email' => 'to_update@example.com','id_estado' => 'ACTIVO']);
        $resp = $this->withHeaders($headers)->putJson(route('protected.auth.usuario.actualizar', $user->id_usuario), [
            'username' => 'actualizado'
        ]);
        $resp->assertStatus(200)->assertJson(['message' => 'Usuario actualizado correctamente']);
        $this->assertDatabaseHas('auth_usuario', ['id_usuario' => $user->id_usuario, 'username' => 'actualizado']);
    }

    public function test_restablecer_password_usuario(): void
    {
        $headers = $this->authHeaders();
        $user = AuthUsuario::factory()->create(['username' => 'reset_user','email' => 'reset_user@example.com','password' => Hash::make('pass12345'),'id_estado' => 'ACTIVO']);
        $oldHash = $user->password;
        $resp = $this->withHeaders($headers)->postJson(route('protected.auth.usuario.restablecer-password', $user->id_usuario));
        $resp->assertStatus(200)->assertJson(['message' => 'Contraseña restablecida correctamente']);
        $this->assertNotEquals($oldHash, $user->fresh()->password);
    }

    public function test_alternar_estado_usuario(): void
    {
        $headers = $this->authHeaders();

        $user = AuthUsuario::factory()->create([
            'username' => 'inactive_user',
            'email' => 'inactive_user@example.com',
            'id_estado' => 'INACTIVO'
        ]);

        $resp = $this->withHeaders($headers)->putJson(route('protected.auth.usuario.alternar-estado', $user->id_usuario));
        $resp->assertStatus(200)->assertJson(['message' => 'Estado de usuario actualizado correctamente']);
        $this->assertDatabaseHas('auth_usuario', ['id_usuario' => $user->id_usuario, 'id_estado' => 'ACTIVO']);

        $resp = $this->withHeaders($headers)->putJson(route('protected.auth.usuario.alternar-estado', $user->id_usuario));
        $resp->assertStatus(200)->assertJson(['message' => 'Estado de usuario actualizado correctamente']);
        $this->assertDatabaseHas('auth_usuario', ['id_usuario' => $user->id_usuario, 'id_estado' => 'INACTIVO']);
    }

    public function test_alternar_estado_2fa_usuario(): void
    {
        $headers = $this->authHeaders();

        $user = AuthUsuario::factory()->create([
            'username' => 'two_factor_user',
            'email' => 'two_factor_user@example.com',
            'id_estado' => 'ACTIVO',
        ]);

        $this->assertDatabaseHas('auth_usuario', [
            'id_usuario' => $user->id_usuario,
            'tfa_activo' => 0,
        ]);

        $this->withHeaders($headers)
            ->putJson(route('protected.auth.usuario.2fa-estado', $user->id_usuario))
            ->assertStatus(422)
            ->assertJson([
                'message' => 'La activación debe realizarse desde la cuenta del usuario',
                'data' => ['tfa_activo' => false],
            ]);

        $user->{'tfa_activo'} = true;
        $user->{'tfa_secret'} = Crypt::encryptString('SECRETKEYEXAMPLE');
        $user->{'2fa_tipo'} = 'authenticator';
        $user->save();

        $resp = $this->withHeaders($headers)->putJson(route('protected.auth.usuario.2fa-estado', $user->id_usuario));
        $resp->assertStatus(200)->assertJson([
            'message' => 'Doble factor deshabilitado',
            'data' => ['tfa_activo' => false],
        ]);
        $this->assertDatabaseHas('auth_usuario', [
            'id_usuario' => $user->id_usuario,
            'tfa_activo' => 0,
        ]);
        $this->assertNull($user->fresh()->{'tfa_secret'});
        $this->assertNull($user->fresh()->{'2fa_tipo'});
    }
}
