<?php

namespace Tests\Feature\Public\Auth;

use App\Models\Auth\AuthUsuario;
use App\Models\Auth\PasswordResetToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createUser(): AuthUsuario
    {
        return AuthUsuario::factory()->create([
            'username' => 'usuario_test',
            'email' => '41d40967-ec85-45bb-960e-2942fc95eff5@yopmail.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_request_password_recovery_sends_code(): void
    {
        $user = $this->createUser();

        $resp = $this->postJson(route('public.auth.request-password-recovery'), [
            'email' => $user->email,
        ]);

        $resp->assertStatus(200)
            ->assertJson([
                'message' => 'Se ha enviado un código de verificación a su correo',
            ]);
        $record = PasswordResetToken::where('email', $user->email)->first();
        $this->assertNotNull($record);
        $this->assertEquals(6, strlen($record->token));
    }

    public function test_request_password_recovery_rate_limit(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('public.auth.request-password-recovery'), [
                'email' => $user->email,
            ])->assertStatus(200);
        }

        $this->postJson(route('public.auth.request-password-recovery'), [
            'email' => $user->email,
        ])->assertStatus(400)
            ->assertJson(['message' => 'Intente nuevamente más tarde']);
    }

    public function test_validate_recovery_token_success_and_resets_password(): void
    {
        $user = $this->createUser();

        $this->postJson(route('public.auth.request-password-recovery'), [
            'email' => $user->email,
        ])->assertStatus(200);

        $token = PasswordResetToken::where('email', $user->email)->value('token');

        $resp = $this->postJson(route('public.auth.validate-recovery-token'), [
            'email' => $user->email,
            'token' => $token,
        ]);

        $resp->assertStatus(200)
            ->assertJson([
                'message' => 'Token verificado. Se envió su nueva contraseña al correo registrado',
            ]);

        $this->assertNull(PasswordResetToken::where('email', $user->email)->first());
    }

    public function test_validate_recovery_token_expired(): void
    {
        $user = $this->createUser();

        PasswordResetToken::updateOrCreate([
            'email' => $user->email,
        ], [
            'token' => '123456',
            'created_at' => now()->subMinutes(10),
        ]);

        $this->postJson(route('public.auth.validate-recovery-token'), [
            'email' => $user->email,
            'token' => '123456',
        ])->assertStatus(401)
            ->assertJson(['message' => 'El token proporcionado ha expirado']);
    }

    public function test_validate_recovery_token_invalid_token_increments_counter(): void
    {
        $user = $this->createUser();

        $this->postJson(route('public.auth.validate-recovery-token'), [
            'email' => $user->email,
            'token' => '000000',
        ])->assertStatus(401);

        $this->postJson(route('public.auth.validate-recovery-token'), [
            'email' => $user->email,
            'token' => '000000',
        ])->assertStatus(401);

        $this->postJson(route('public.auth.validate-recovery-token'), [
            'email' => $user->email,
            'token' => '000000',
        ])->assertStatus(401);

        $this->postJson(route('public.auth.validate-recovery-token'), [
            'email' => $user->email,
            'token' => '000000',
        ])->assertStatus(400)
            ->assertJson(['message' => 'Intente nuevamente más tarde']);
    }
}
