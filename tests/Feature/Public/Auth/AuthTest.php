<?php

namespace Tests\Feature\Public\Auth;

use App\Models\Auth\AuthUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const REFRESH_TOKEN_MIN_LENGTH = 32;

    private function createUserIfNotExist(): void
    {
        if (! AuthUsuario::where('email', 'test@example.com')->exists()) {
            AuthUsuario::create([
                'username' => 'usuario_test',
                'email' => 'test@example.com',
                'password' => Hash::make('password123'),
            ]);
        }
    }

    public function test_login_with_valid_credentials_returns_token_s(): void
    {
        $this->createUserIfNotExist();

        $credentials = [
            'identifier' => 'test@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ])
            ->assertJson([
                'token_type' => 'bearer',
            ]);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertIsString($response->json('access_token'));
        $this->assertNotEmpty($response->json('refresh_token'));
        $this->assertIsString($response->json('refresh_token'));
        $this->assertIsInt($response->json('expires_in'));
        $this->assertGreaterThan(0, $response->json('expires_in'));
    }

    public function test_login_with_invalid_email_returns_validation_error(): void
    {
        $credentials = [
            'identifier' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(401);
    }

    public function test_login_with_wrong_password_returns_unauthorized(): void
    {
        $this->createUserIfNotExist();

        $credentials = [
            'identifier' => 'test@example.com',
            'password' => 'wrong_password',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Credenciales inválidas',
            ]);
    }

    public function test_login_without_email_returns_validation_error(): void
    {
        $credentials = [
            'password' => 'password123',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_login_without_password_returns_validation_error(): void
    {
        $credentials = [
            'identifier' => 'test@example.com',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_with_invalid_email_format_returns_validation_error(): void
    {
        $credentials = [
            'identifier' => 'invalid-email-format',
            'password' => 'password123',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(401);
    }

    public function test_login_with_empty_credentials_returns_validation_errors(): void
    {
        $credentials = [
            'identifier' => '',
            'password' => '',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identifier', 'password']);
    }

    public function test_login_response_matches_documentation_structure(): void
    {
        $this->createUserIfNotExist();

        $credentials = [
            'identifier' => 'admin@example.com',
            'password' => 'password123',
        ];

        $response = $this->postJson(route('public.auth.login'), $credentials);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ]);

        $responseData = $response->json();

        $this->assertIsString($responseData['access_token']);
        $this->assertIsString($responseData['refresh_token']);
        $this->assertGreaterThanOrEqual(self::REFRESH_TOKEN_MIN_LENGTH, strlen($responseData['refresh_token']));
        $this->assertEquals('bearer', $responseData['token_type']);
        $this->assertIsNumeric($responseData['expires_in']);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/', $responseData['access_token']);
    }

    public function test_login_with_authenticator_requires_totp(): void
    {
        Cache::flush();

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = AuthUsuario::updateOrCreate(
            ['email' => 'twofa@example.com'],
            [
                'username' => 'twofa_user',
                'password' => Hash::make('password123'),
                'tfa_activo' => true,
                'tfa_secret' => Crypt::encryptString($secret),
                'temporal_token' => null,
                '2fa_tipo' => 'authenticator',
            ]
        );

        $response = $this->postJson(route('public.auth.login'), [
            'identifier' => 'twofa@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Ingresa el código generado por Google Authenticator',
            ]);

        $methods = $response->json('methods');
        $this->assertIsArray($methods);
        $this->assertSame(['authenticator'], $methods);

        $user->refresh();
        $this->assertNull($user->temporal_token);
        $this->assertNotNull($user->{'tfa_secret'});
    }

    public function test_login_with_email_two_factor_sends_code(): void
    {
        Cache::flush();

        $user = AuthUsuario::updateOrCreate(
            ['email' => 'twofa-email-login@example.com'],
            [
                'username' => 'twofa_email_login',
                'password' => Hash::make('password123'),
                'tfa_activo' => true,
                '2fa_tipo' => 'email',
                'tfa_secret' => null,
                'temporal_token' => null,
            ]
        );

        $response = $this->postJson(route('public.auth.login'), [
            'identifier' => 'twofa-email-login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Ingresa el código enviado a tu correo',
                'methods' => ['email'],
            ]);

        $user->refresh();
        $this->assertNotNull($user->temporal_token);
        $this->assertEquals(6, strlen($user->temporal_token));
    }

    public function test_validate_2fa_token_success_returns_jwt_and_clears_temp_token(): void
    {
        Cache::flush();
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = AuthUsuario::updateOrCreate(
            ['email' => 'twofa2@example.com'],
            [
                'username' => 'twofa_user2',
                'password' => Hash::make('password123'),
                'tfa_activo' => true,
                'tfa_secret' => Crypt::encryptString($secret),
                'temporal_token' => null,
                '2fa_tipo' => 'authenticator',
            ]
        );

        $token = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson(route('public.auth.validate-2fa-token'), [
            'identifier' => 'twofa2@example.com',
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);

        $user->refresh();
        $this->assertNull($user->temporal_token);
    }

    public function test_validate_2fa_token_accepts_email_code(): void
    {
        Cache::flush();

        $user = AuthUsuario::updateOrCreate(
            ['email' => 'twofa-email@example.com'],
            [
                'username' => 'twofa_email_user',
                'password' => Hash::make('password123'),
                'tfa_activo' => true,
                'tfa_secret' => null,
                'temporal_token' => '654321',
                'temporal_token_expira_en' => now()->addMinutes(5),
                '2fa_tipo' => 'email',
            ]
        );

        $response = $this->postJson(route('public.auth.validate-2fa-token'), [
            'identifier' => 'twofa-email@example.com',
            'token' => '654321',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);

        $user->refresh();
        $this->assertNull($user->temporal_token);
    }

    public function test_validate_2fa_token_wrong_token_increments_attempts_and_returns_401(): void
    {
        Cache::flush();
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        AuthUsuario::updateOrCreate(
            ['email' => 'twofa3@example.com'],
            [
                'username' => 'twofa_user3',
                'password' => Hash::make('password123'),
                'tfa_activo' => true,
                'tfa_secret' => Crypt::encryptString($secret),
                'temporal_token' => null,
                '2fa_tipo' => 'authenticator',
            ]
        );

        $response = $this->postJson(route('public.auth.validate-2fa-token'), [
            'identifier' => 'twofa3@example.com',
            'token' => '000000',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'El token proporcionado es inválido',
            ]);
    }

    public function test_validate_2fa_token_blocked_after_three_attempts(): void
    {
        Cache::flush();
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = AuthUsuario::updateOrCreate(
            ['email' => 'twofa4@example.com'],
            [
                'username' => 'twofa_user4',
                'password' => Hash::make('password123'),
                'tfa_activo' => true,
                'tfa_secret' => Crypt::encryptString($secret),
                'temporal_token' => null,
                '2fa_tipo' => 'authenticator',
            ]
        );

        // 3 intentos fallidos
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('public.auth.validate-2fa-token'), [
                'identifier' => 'twofa4@example.com',
                'token' => '000000',
            ])->assertStatus(401);
        }

        // cuarto intento bloqueado
        $response = $this->postJson(route('public.auth.validate-2fa-token'), [
            'identifier' => 'twofa4@example.com',
            'token' => $google2fa->getCurrentOtp($secret),
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Intente nuevamente más tarde',
            ]);

        $user->refresh();
        $this->assertNotNull($user->{'tfa_secret'});
    }
}
