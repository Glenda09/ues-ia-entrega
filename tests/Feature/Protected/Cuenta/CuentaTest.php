<?php

namespace Tests\Feature\Protected\Cuenta;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use PragmaRX\Google2FA\Google2FA;

class CuentaTest extends TestCase
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

        // Asignar perfil administrador para permisos completos
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

    public function test_info_returns_account_data(): void
    {
        $headers = $this->authHeaders();

        $res = $this->withHeaders($headers)
            ->getJson(route('protected.cuenta.info'))
            ->assertStatus(200)
            ->assertJsonStructure([
                'id_usuario',
                'username',
                'email',
                'estado',
                'perfiles',
                'menu',
                'permisos',
                'two_factor_status',
            ])
            ->json();

        $this->assertEquals('user@example.com', $res['email']);
        $this->assertEquals('user_test', $res['username']);
        $this->assertIsArray($res['perfiles']);
        $this->assertIsArray($res['menu']);
        $this->assertIsArray($res['permisos']);
        $this->assertIsBool($res['two_factor_status']);
    }

    public function test_setup_and_enable_2fa(): void
    {
        $headers = $this->authHeaders();

        $setup = $this->withHeaders($headers)
            ->postJson(route('protected.cuenta.2fa-setup'))
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'data' => ['secret', 'otpauth_url']])
            ->json();

        $secret = $setup['data']['secret'];
        $google2fa = new Google2FA();
        $token = $google2fa->getCurrentOtp($secret);

        $this->withHeaders($headers)
            ->putJson(route('protected.cuenta.2fa'), [
                'mode' => 'authenticator',
                'token' => $token,
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'data' => ['tfa_activo', '2fa_tipo']])
            ->assertJsonPath('data.tfa_activo', true)
            ->assertJsonPath('data.2fa_tipo', 'authenticator');

        $usuario = AuthUsuario::where('email', 'user@example.com')->first();
        $this->assertNotNull($usuario?->{'tfa_secret'});
        $this->assertEquals('authenticator', $usuario?->{'2fa_tipo'});

        $this->withHeaders($headers)
            ->putJson(route('protected.cuenta.2fa'), [
                'mode' => 'disabled',
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'data' => ['tfa_activo', '2fa_tipo']])
            ->assertJsonPath('data.tfa_activo', false)
            ->assertJsonPath('data.2fa_tipo', null);

        $usuario = AuthUsuario::where('email', 'user@example.com')->first();
        $this->assertNull($usuario?->{'tfa_secret'});
        $this->assertNull($usuario?->{'2fa_tipo'});
    }

    public function test_enable_email_two_factor(): void
    {
        $headers = $this->authHeaders();

        $this->withHeaders($headers)
            ->putJson(route('protected.cuenta.2fa'), [
                'mode' => 'email',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.tfa_activo', true)
            ->assertJsonPath('data.2fa_tipo', 'email');

        $usuario = AuthUsuario::where('email', 'user@example.com')->first();
        $this->assertEquals('email', $usuario?->{'2fa_tipo'});
        $this->assertNull($usuario?->{'tfa_secret'});
    }
}
