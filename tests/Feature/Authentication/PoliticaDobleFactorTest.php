<?php

namespace Tests\Feature\Authentication;

use App\Models\Auth\AuthUsuario;
use App\Services\Auth\PermisosEfectivosService;
use App\Services\EmailService;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PoliticaDobleFactorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.two_factor_enforce_all' => true, 'auth.two_factor_required_method' => 'email']);
        $this->mock(EmailService::class)->shouldReceive('sendHtmlEmail')->zeroOrMoreTimes();
        $this->mock(PermisosEfectivosService::class)->shouldReceive('paraUsuario')->andReturn(['root']);
    }

    private function usuario(): AuthUsuario
    {
        return AuthUsuario::create([
            'username' => 'politica',
            'email' => 'politica@example.com',
            'password' => Hash::make('password123'),
            'id_estado' => 'ACTIVO',
            'tfa_activo' => false,
        ]);
    }

    private function iniciarIngreso(AuthUsuario $usuario): void
    {
        $this->postJson(route('public.auth.login'), ['identifier' => $usuario->email, 'password' => 'password123'])
            ->assertStatus(202)->assertJsonPath('methods.0', 'email')->assertJsonMissingPath('access_token');
        $usuario->refresh();
    }

    public function test_politica_exige_codigo_antes_de_emitir_jwt_y_activa_correo(): void
    {
        $usuario = $this->usuario();
        $this->iniciarIngreso($usuario);
        $this->assertFalse((bool) $usuario->{'tfa_activo'});
        $this->assertNotNull($usuario->temporal_token_expira_en);
        $codigo = $usuario->temporal_token;
        $respuesta = $this->postJson(route('public.auth.validate-2fa-token'), ['identifier' => $usuario->email, 'token' => $codigo])
            ->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
        $usuario->refresh();
        $this->assertTrue((bool) $usuario->{'tfa_activo'});
        $this->assertSame('email', $usuario->{'2fa_tipo'});
        $this->assertNull($usuario->temporal_token);
        $this->withToken($respuesta->json('access_token'))->putJson(route('protected.cuenta.2fa'), ['mode' => 'disabled'])->assertUnprocessable();
        $this->withToken($respuesta->json('access_token'))->getJson(route('protected.cuenta.info'))
            ->assertOk()->assertJsonPath('two_factor_required', true)->assertJsonPath('two_factor_can_disable', false)->assertJsonPath('two_factor_setup_pending', false);
    }

    public function test_rechaza_codigo_vencido_sin_emitir_jwt(): void
    {
        $usuario = $this->usuario();
        $this->iniciarIngreso($usuario);
        $codigo = $usuario->temporal_token;
        $this->travel(6)->minutes();
        $this->postJson(route('public.auth.validate-2fa-token'), ['identifier' => $usuario->email, 'token' => $codigo])
            ->assertUnauthorized()->assertJsonMissingPath('access_token');
        $this->assertFalse((bool) $usuario->fresh()->{'tfa_activo'});
    }

    public function test_authenticator_obligatorio_bloquea_incluso_root_hasta_confirmar(): void
    {
        config(['auth.two_factor_required_method' => 'authenticator']);
        $usuario = $this->usuario();
        $this->iniciarIngreso($usuario);
        $respuesta = $this->postJson(route('public.auth.validate-2fa-token'), ['identifier' => $usuario->email, 'token' => $usuario->temporal_token])->assertOk();
        $this->withToken($respuesta->json('access_token'));
        $this->getJson(route('protected.personalizacion.mostrar'))->assertForbidden()->assertJsonPath('code', 'DOBLE_FACTOR_CONFIGURACION_PENDIENTE');
        $this->getJson(route('protected.cuenta.info'))->assertOk()->assertJsonPath('two_factor_setup_pending', true);
        $this->putJson(route('protected.cuenta.2fa'), ['mode' => 'email'])->assertUnprocessable();
        $this->putJson(route('protected.cuenta.2fa'), ['mode' => 'disabled'])->assertUnprocessable();
        $setup = $this->postJson(route('protected.cuenta.2fa-setup'))->assertOk();
        $codigo = (new Google2FA)->getCurrentOtp($setup->json('data.secret'));
        $this->putJson(route('protected.cuenta.2fa'), ['mode' => 'authenticator', 'token' => $codigo])->assertOk();
        $this->getJson(route('protected.cuenta.info'))->assertOk()->assertJsonPath('two_factor_setup_pending', false);
        $this->getJson(route('protected.personalizacion.mostrar'))->assertOk();
        $this->postJson(route('public.auth.login'), ['identifier' => $usuario->email, 'password' => 'password123'])
            ->assertStatus(202)->assertJsonPath('methods.0', 'authenticator');
    }

    public function test_politica_desactivada_conserva_ingreso_sin_doble_factor(): void
    {
        config(['auth.two_factor_enforce_all' => false]);
        $usuario = $this->usuario();
        $this->postJson(route('public.auth.login'), ['identifier' => $usuario->email, 'password' => 'password123'])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_metodo_invalido_usa_correo_y_no_desactiva_la_politica(): void
    {
        config(['auth.two_factor_required_method' => 'otro']);
        $usuario = $this->usuario();
        $this->iniciarIngreso($usuario);
    }
}
