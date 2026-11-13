<?php

namespace Tests\Feature\Personalizacion;

use App\Models\App\AppPersonalizacion;
use App\Models\Auth\AuthUsuario;
use App\Services\App\PersonalizacionService;
use App\Services\Auth\PermisosEfectivosService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PersonalizacionTest extends TestCase
{
    private function autorizar(array $permisos = ['root']): void
    {
        $this->mock(PermisosEfectivosService::class)->shouldReceive('paraUsuario')->andReturn($permisos);
        $this->withToken(JWTAuth::fromUser(AuthUsuario::firstOrFail()));
    }

    public function test_publica_sin_preset_ni_fila_inicial(): void
    {
        $respuesta = $this->getJson(route('public.personalizacion.mostrar'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(array_fill_keys(array_keys(PersonalizacionService::TEXTOS), null), $respuesta->json('data.textos'));
        $this->assertSame(array_fill_keys(PersonalizacionService::ASSETS, null), $respuesta->json('data.assets'));
        $respuesta->assertJsonPath('data.color_primario', null);
        $this->assertDatabaseCount('app_personalizacion', 0);
    }

    public function test_exige_autenticacion_y_permisos_por_operacion(): void
    {
        $this->getJson(route('protected.personalizacion.mostrar'))->assertUnauthorized();
        $this->putJson(route('protected.personalizacion.guardar'), [])->assertUnauthorized();
        $this->autorizar(['personalizacion_ver']);
        $this->getJson(route('protected.personalizacion.mostrar'))->assertOk();
        $this->putJson(route('protected.personalizacion.guardar'), ['textos' => ['nombre' => 'Proyecto']])->assertForbidden();
        $this->postJson(route('protected.personalizacion.subir', ['slot' => 'logo']), [])->assertForbidden();
        $this->deleteJson(route('protected.personalizacion.quitar', ['slot' => 'logo']))->assertForbidden();
        $this->assertDatabaseCount('app_personalizacion', 0);
    }

    public function test_edicion_parcial_y_limpieza_no_aplican_preset(): void
    {
        $this->autorizar(['personalizacion_editar']);
        $this->putJson(route('protected.personalizacion.guardar'), ['textos' => ['nombre' => 'Mi proyecto', 'descripcion' => 'Descripción propia'], 'color_primario' => '#174b3a'])
            ->assertOk()->assertJsonPath('data.color_primario', '#174B3A');
        $this->putJson(route('protected.personalizacion.guardar'), ['textos' => ['nombre' => null], 'color_primario' => null])
            ->assertOk()->assertJsonPath('data.textos.nombre', null)->assertJsonPath('data.textos.descripcion', 'Descripción propia')->assertJsonPath('data.color_primario', null);
        $this->assertDatabaseCount('app_personalizacion', 1);
        $this->getJson(route('public.personalizacion.mostrar'))->assertOk()->assertJsonMissingPath('data.clave')->assertJsonMissingPath('data.created_at');
    }

    public function test_rechaza_textos_y_colores_invalidos(): void
    {
        $this->autorizar();
        foreach ([['textos' => ['nombre' => '<script>alert(1)</script>']], ['textos' => ['extra' => 'valor']], ['textos' => ['nombre' => str_repeat('a', 61)]], ['color_primario' => '#fff'], ['color_primario' => '#FFFFFF'], ['textos' => null], ['color_primario' => ['#123456']]] as $datos) {
            $this->putJson(route('protected.personalizacion.guardar'), $datos)->assertUnprocessable();
        }
        $this->assertDatabaseCount('app_personalizacion', 0);
    }

    public function test_imagen_publica_reemplazo_y_retiro(): void
    {
        Storage::fake(PersonalizacionService::DISCO);
        $this->autorizar();
        $this->postJson(route('protected.personalizacion.subir', ['slot' => 'logo']), ['archivo' => UploadedFile::fake()->image('logo.png', 20, 20)])->assertOk();
        $anterior = AppPersonalizacion::firstOrFail()->assets['logo'];
        $rutaAnterior = route('public.personalizacion.asset', ['slot' => 'logo', 'version' => $anterior['hash']]);
        $this->get($rutaAnterior)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $respuesta = $this->postJson(route('protected.personalizacion.subir', ['slot' => 'logo']), ['archivo' => UploadedFile::fake()->image('nuevo.jpg', 30, 30)])->assertOk();
        Storage::disk(PersonalizacionService::DISCO)->assertMissing($anterior['ruta']);
        $this->get($rutaAnterior)->assertNotFound();
        $this->get($respuesta->json('data.assets.logo'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $rutaNueva = AppPersonalizacion::firstOrFail()->assets['logo']['ruta'];
        $this->deleteJson(route('protected.personalizacion.quitar', ['slot' => 'logo']))->assertOk()->assertJsonPath('data.assets.logo', null);
        Storage::disk(PersonalizacionService::DISCO)->assertMissing($rutaNueva);
        $this->deleteJson(route('protected.personalizacion.quitar', ['slot' => 'logo']))->assertOk();
    }

    public function test_rechaza_archivos_activos_falsificados_y_grandes(): void
    {
        Storage::fake(PersonalizacionService::DISCO);
        $this->autorizar();
        foreach (['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', '<?php echo 1;', 'no es imagen'] as $contenido) {
            $this->postJson(route('protected.personalizacion.subir', ['slot' => 'logo']), ['archivo' => UploadedFile::fake()->createWithContent('logo.png', $contenido)])->assertUnprocessable();
        }
        $this->postJson(route('protected.personalizacion.subir', ['slot' => 'favicon']), ['archivo' => UploadedFile::fake()->create('icon.png', 257)])->assertUnprocessable();
        $this->postJson('/api/personalizacion/assets/inexistente', ['archivo' => UploadedFile::fake()->image('logo.png')])->assertNotFound();
        $this->assertDatabaseCount('app_personalizacion', 0);
        $this->assertSame([], Storage::disk(PersonalizacionService::DISCO)->allFiles());
    }

    public function test_fallo_de_persistencia_conserva_imagen_anterior_y_limpia_la_nueva(): void
    {
        Storage::fake(PersonalizacionService::DISCO);
        $servicio = app(PersonalizacionService::class);
        $servicio->subir('logo', UploadedFile::fake()->image('logo.png'));
        $anterior = AppPersonalizacion::firstOrFail()->assets['logo'];
        $evento = 'eloquent.saving: '.AppPersonalizacion::class;
        \Illuminate\Support\Facades\Event::listen($evento, function () {
            throw new \RuntimeException('Fallo de escritura simulado');
        });
        try {
            $servicio->subir('logo', UploadedFile::fake()->image('nuevo.png'));
            $this->fail('La escritura debía fallar');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fallo de escritura simulado', $error->getMessage());
        } finally {
            \Illuminate\Support\Facades\Event::forget($evento);
        }
        $this->assertSame($anterior, AppPersonalizacion::firstOrFail()->assets['logo']);
        $this->assertSame([$anterior['ruta']], Storage::disk(PersonalizacionService::DISCO)->allFiles());
    }

    public function test_nombre_configurado_en_emisor_2fa_y_correo_de_recuperacion(): void
    {
        app(PersonalizacionService::class)->guardar(['textos' => ['nombre' => 'Portal Ciudadano']]);
        $usuario = AuthUsuario::firstOrFail();
        $configuracion = app(\App\Services\Auth\TwoFactorService::class)->createSecret($usuario);
        $this->assertStringContainsString('issuer=Portal%20Ciudadano', $configuracion['otpauth_url']);
        $this->mock(\App\Services\EmailService::class)->shouldReceive('sendHtmlEmail')->once()
            ->withArgs(fn ($destino, $asunto, $html) => $destino === $usuario->email && str_contains($html, 'Equipo de Portal Ciudadano'));
        $this->postJson(route('public.auth.request-password-recovery'), ['email' => $usuario->email])->assertOk();
    }

    public function test_shell_incluye_marca_actual_y_serializa_textos_de_forma_segura(): void
    {
        app(PersonalizacionService::class)->guardar(['textos' => ['nombre' => 'Proyecto & Ciudad', 'titulo_pestana' => 'Portal ciudadano', 'descripcion' => 'Información del proyecto']]);
        foreach (['/home', '/login', '/two-factor', '/forgot-password', '/personalizacion'] as $ruta) {
            $this->get($ruta)->assertOk()->assertSee('<title>Portal ciudadano</title>', false)->assertSee('window.__PERSONALIZACION__', false)->assertSee('Información del proyecto');
        }
        AppPersonalizacion::firstOrFail()->update(['textos' => ['nombre' => '</script><script>alert(1)</script>']]);
        $this->get('/login')->assertOk()->assertDontSee('</script><script>alert(1)</script>', false);
    }
}
