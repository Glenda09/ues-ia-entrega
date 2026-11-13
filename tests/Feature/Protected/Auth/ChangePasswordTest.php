<?php

namespace Tests\Feature\Protected\Auth;

use App\Models\Auth\AuthUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function createAndLogin(): array
    {
        $user = AuthUsuario::factory()->create([
            'username' => 'usuario_test',
            'email' => 'b78c3a09-7064-4b8c-82da-283ce564a78f@yopmail.com',
            'password' => Hash::make('password123'),
        ]);

        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => $user->email,
            'password' => 'password123',
        ])->json();

        return [$user, $login['access_token']];
    }

    public function test_change_password_success(): void
    {
        [$user, $token] = $this->createAndLogin();

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('protected.auth.change-password'), [
                'current_password' => 'password123',
                'new_password' => 'Newpass123',
                'password_confirmation' => 'Newpass123',
            ]);

        $resp->assertStatus(200)
            ->assertJson(['message' => 'Contraseña actualizada correctamente']);

        $this->assertTrue(Hash::check('Newpass123', $user->fresh()->password));
    }

    public function test_change_password_wrong_current_password(): void
    {
    [, $token] = $this->createAndLogin();

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('protected.auth.change-password'), [
                'current_password' => 'invalid',
                'new_password' => 'Newpass123',
                'password_confirmation' => 'Newpass123',
            ]);

        $resp->assertStatus(401)
            ->assertJson(['message' => 'La contraseña actual no es correcta']);
    }

    public function test_change_password_confirmation_mismatch(): void
    {
    [, $token] = $this->createAndLogin();

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('protected.auth.change-password'), [
                'current_password' => 'password123',
                'new_password' => 'Newpass123',
                'password_confirmation' => 'Different123',
            ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }
}
