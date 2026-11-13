<?php

namespace Tests\Unit\Public\Auth;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Models\Auth\AuthUsuario;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_token(): void
    {
        AuthUsuario::create([
            'id_usuario' => Str::uuid(),
            'username' => 'admin',
            'email' => 'test-user@example.com',
            'password' => Hash::make('password123'),
            'fecha_verificacion' => now(),
            'id_estado' => 'ACTIVO',
            'created_at' => now(),
        ]);

        $credentials = [
            'identifier' => 'test-user@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
            ])
            ->assertJson([
                'token_type' => 'bearer',
            ]);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertIsString($response->json('access_token'));
        $this->assertIsInt($response->json('expires_in'));
        $this->assertGreaterThan(0, $response->json('expires_in'));
    }
}
