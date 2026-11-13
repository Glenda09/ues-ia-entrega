<?php

namespace Tests\Feature\Protected\Auth;

use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function getJwtToken(array $credentials = []): array
    {
        $email = $credentials['email'] ?? 'user@example.com';
        $password = $credentials['password'] ?? 'password123';

        AuthUsuario::create([
            'username' => 'user_test',
            'email' => $email,
            'password' => Hash::make($password),
            'id_estado' => 'ACTIVO',
        ]);

        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => $email,
            'password' => $password,
        ])->assertStatus(200);

        return [
            'access_token' => $login['access_token'],
            'refresh_token' => $login['refresh_token'],
        ];
    }

    public function test_refresh_token_success(): void
    {
        $refreshToken = $this->getJwtToken()['refresh_token'];

        $response = $this->postJson(route('public.auth.refresh-token'), [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in'])
            ->assertJson(['token_type' => 'bearer']);
    }

    public function test_refresh_token_without_token_returns_401(): void
    {
        $response = $this->postJson(route('public.auth.refresh-token'));

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_logout_success(): void
    {
        $token = $this->getJwtToken()['access_token'];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('protected.auth.logout'));

        $response->assertStatus(200)
            ->assertJson(['message' => 'Sesión cerrada correctamente']);
    }
}
