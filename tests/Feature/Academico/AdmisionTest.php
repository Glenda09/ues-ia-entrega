<?php

namespace Tests\Feature\Academico;

use App\Models\Academico\CarnetCorrelativo;
use App\Models\Academico\EntregaAcceso;
use App\Models\Academico\Facultad;
use App\Models\Academico\OrdenPagoIngreso;
use App\Models\Academico\Sede;
use App\Models\Academico\VerificacionCorreoIngreso;
use App\Models\Auth\AuthUsuario;
use App\Services\Academico\ConvocatoriaService;
use App\Services\Academico\IdentidadAccesoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * EXP-01, EXP-02 y EST-01 (backend de acceso).
 */
class AdmisionTest extends TestCase
{
    use AcademicoHelpers;

    private function preparar(): array
    {
        Storage::fake('local');
        $esc = $this->escenario('ADM');
        $sede = Sede::create(['codigo' => 'SS', 'nombre' => 'San Salvador']);
        $fac = Facultad::create(['codigo' => 'FIA', 'nombre' => 'Ingeniería']);
        $esc['carrera']->update(['id_facultad' => $fac->id_facultad]);
        $esc['carrera']->sedes()->sync([$sede->id_sede]);
        $conv = app(ConvocatoriaService::class)->crear($esc['carrera'], [
            'codigo' => 'ADM26', 'titulo_publico' => 'Admisión 2026', 'inicio' => '2026-03-01 00:00', 'fin' => '2026-04-01 00:00',
            'ofertas' => [
                ['id_sede' => $sede->id_sede, 'id_facultad' => $fac->id_facultad, 'modalidad_estudio' => 'presencial', 'modalidad_ingreso' => 'general'],
                ['id_sede' => $sede->id_sede, 'id_facultad' => $fac->id_facultad, 'modalidad_estudio' => 'presencial', 'modalidad_ingreso' => 'especial'],
            ],
        ], $esc['admin']);
        $conv = app(ConvocatoriaService::class)->actualizar($conv, ['version' => 1, 'estado' => 'publicada'], $esc['admin']);

        return $esc + ['conv' => $conv, 'sede' => $sede, 'fac' => $fac];
    }

    private function registrarAspirante(string $email): array
    {
        $this->postJson('/api/publico/admision/verificaciones', ['email' => $email])->assertOk();
        $codigo = null;
        // El código viaja cifrado en la entrega; en pruebas se obtiene del registro de entrega.
        $entrega = EntregaAcceso::query()->where('destinatario', $email)->where('tipo', 'verificacion')->latest('id_entrega')->first();
        $codigo = $entrega && $entrega->secreto_cifrado ? Crypt::decryptString($entrega->secreto_cifrado) : null;
        if (! $codigo) {
            // La cola sync ya envió y borró el secreto: reconstruirlo no es posible, se usa un código conocido.
            $codigo = '123456';
            VerificacionCorreoIngreso::query()->where('email', $email)->update(['codigo_hash' => hash('sha256', $email.'|'.$codigo)]);
        }
        $this->postJson('/api/publico/admision/registros', ['email' => $email, 'codigo_verificacion' => $codigo, 'password' => 'Secreta123', 'password_confirmation' => 'Secreta123'])->assertStatus(201);
        $u = AuthUsuario::where('email', $email)->firstOrFail();

        return [$u, $this->headers($u)];
    }

    private function pdf(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, '%PDF-1.4
1 0 obj<<>>endobj
trailer<<>>
%%EOF
');
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('foto.jpg', 150, 195);
    }

    private function datos(array $e, array $extra = []): array
    {
        return array_merge([
            'nombres' => 'María Rosa', 'primer_apellido' => 'Sibrián', 'segundo_apellido' => 'Hernández', 'fecha_nacimiento' => '2000-05-01',
            'nacionalidad' => 'salvadoreña', 'dui' => '01234567-8', 'nie' => '123456', 'institucion_procedencia' => 'INFRAMEN', 'estado_graduacion' => 'graduado',
            'modalidad_estudio' => 'presencial', 'modalidad_ingreso' => 'general', 'id_sede' => $e['sede']->id_sede, 'id_facultad' => $e['fac']->id_facultad,
            'id_carrera' => $e['carrera']->id_carrera, 'referencia_comprobante' => 'REF-1',
        ], $extra);
    }

    private function completarFormulario(array $e, array $h, array $datos): array
    {
        $id = $e['conv']->id_convocatoria;
        $b = $this->withHeaders($h)->patchJson('/api/academico/mi-borrador-ingreso', ['convocatoria' => $id, 'version' => 0, 'etapa' => 3, 'datos' => $datos])->assertOk()->json('data');
        $this->assertSame('pendiente', $b['orden_pago']['estado']);
        $v = $b['borrador']['version'];
        $docs = ['fotografia' => $this->foto(), 'dui' => $this->pdf('dui.pdf'), 'titulo_bachiller' => $this->pdf('t.pdf'), 'comprobante_pago' => $this->pdf('c.pdf')];
        if (($datos['nacionalidad'] ?? '') !== 'salvadoreña') {
            unset($docs['dui']);
            $docs['pasaporte'] = $this->pdf('p.pdf');
        }
        foreach ($docs as $codigo => $archivo) {
            $b = $this->withHeaders($h)->post('/api/academico/mi-borrador-ingreso/anexos', ['convocatoria' => $id, 'codigo_requisito' => $codigo, 'version' => $v, 'archivo' => $archivo])->assertOk()->json('data');
            $v = $b['borrador']['version'];
        }

        return $b;
    }

    public function test_exp01_registro_formulario_documentos_envio_idempotente(): void
    {
        $e = $this->preparar();
        [$u, $h] = $this->registrarAspirante('maria@aspirante.test');
        $this->assertDatabaseHas('auth_usuario_perfil', ['id_usuario' => $u->id_usuario, 'codigo_perfil' => 'academico_aspirante']);
        // Correo existente: respuesta genérica, sin nueva cuenta.
        $this->postJson('/api/publico/admision/verificaciones', ['email' => 'admin@example.com'])->assertOk();

        $id = $e['conv']->id_convocatoria;
        $this->withHeaders($h)->patchJson('/api/academico/mi-borrador-ingreso', ['convocatoria' => $id, 'version' => 1, 'etapa' => 1, 'datos' => []])->assertStatus(409);
        $this->withHeaders($h)->patchJson('/api/academico/mi-borrador-ingreso', ['convocatoria' => $id, 'version' => 0, 'etapa' => 1, 'datos' => ['campo_x' => 1]])->assertStatus(422);
        // Combinación académica inválida.
        $this->withHeaders($h)->patchJson('/api/academico/mi-borrador-ingreso', ['convocatoria' => $id, 'version' => 0, 'etapa' => 3, 'datos' => $this->datos($e, ['modalidad_estudio' => 'distancia'])])->assertStatus(422);

        $b = $this->completarFormulario($e, $h, $this->datos($e));
        $this->assertContains('dui', $b['requisitos_aplicables']);
        $this->assertNotContains('pasaporte', $b['requisitos_aplicables']);
        // Foto con dimensiones incorrectas.
        $this->withHeaders($h)->post('/api/academico/mi-borrador-ingreso/anexos', ['convocatoria' => $id, 'codigo_requisito' => 'fotografia', 'version' => $b['borrador']['version'], 'archivo' => UploadedFile::fake()->image('f.jpg', 200, 200)])->assertStatus(422)->assertJsonValidationErrors('archivo');

        // Requisitos desactualizados.
        $this->withHeaders($h + ['Idempotency-Key' => 'k0'])->postJson("/api/academico/convocatorias/$id/mis-solicitudes", ['version_requisitos' => 99])->assertStatus(409)->assertJsonPath('code', 'CONVOCATORIA_ACTUALIZADA');
        $r = $this->withHeaders($h + ['Idempotency-Key' => 'k1'])->postJson("/api/academico/convocatorias/$id/mis-solicitudes", ['version_requisitos' => 1])->assertStatus(201);
        $folio = $r->json('data.folio');
        $this->withHeaders($h + ['Idempotency-Key' => 'k1'])->postJson("/api/academico/convocatorias/$id/mis-solicitudes", ['version_requisitos' => 1])->assertOk()->assertJsonPath('data.folio', $folio);
        $this->withHeaders($h + ['Idempotency-Key' => 'k2'])->postJson("/api/academico/convocatorias/$id/mis-solicitudes", ['version_requisitos' => 1])->assertOk()->assertJsonPath('data.folio', $folio);
        $this->assertDatabaseCount('solicitud_ingreso', 1);
        $this->assertDatabaseCount('solicitud_ingreso_revision', 1);
        $this->assertSame(1, OrdenPagoIngreso::count());

        // Aspirante ajeno no ve la solicitud.
        [, $h2] = $this->registrarAspirante('otro@aspirante.test');
        $sid = $r->json('data.id_solicitud');
        $this->withHeaders($h2)->getJson("/api/academico/mis-solicitudes/$sid")->assertStatus(404);
    }

    public function test_exp01_extranjero_recibe_pasaporte_y_envio_fuera_de_ventana_falla(): void
    {
        $e = $this->preparar();
        [, $h] = $this->registrarAspirante('juan@aspirante.test');
        $b = $this->completarFormulario($e, $h, $this->datos($e, ['nacionalidad' => 'guatemalteca', 'dui' => null, 'pais_documento' => 'GT', 'pasaporte' => 'P123']));
        $this->assertContains('pasaporte', $b['requisitos_aplicables']);
        $this->assertNotContains('dui', $b['requisitos_aplicables']);

        Carbon::setTestNow('2026-04-01 06:00:00'); // = 2026-04-01 00:00 local (cierre)
        $h = $this->headers(AuthUsuario::where('email', 'juan@aspirante.test')->first());
        $this->withHeaders($h + ['Idempotency-Key' => 'k1'])->postJson("/api/academico/convocatorias/{$e['conv']->id_convocatoria}/mis-solicitudes", ['version_requisitos' => 1])->assertStatus(409)->assertJsonPath('code', 'CONVOCATORIA_CERRADA');
        $this->assertDatabaseCount('solicitud_ingreso', 0);
    }

    public function test_exp02_observar_corregir_aceptar_y_carnet(): void
    {
        Mail::fake();
        $e = $this->preparar();
        [$u, $h] = $this->registrarAspirante('maria@aspirante.test');
        $this->completarFormulario($e, $h, $this->datos($e));
        $sid = $this->withHeaders($h + ['Idempotency-Key' => 'k1'])->postJson("/api/academico/convocatorias/{$e['conv']->id_convocatoria}/mis-solicitudes", ['version_requisitos' => 1])->json('data.id_solicitud');

        $gestor = $this->gestorDe($e['carrera'], ['academico_gestor_expediente']);
        $hg = $this->headers($gestor);
        $ajeno = $this->gestorDe($this->carrera('OTRA'), ['academico_gestor_expediente']);
        $this->withHeaders($this->headers($ajeno))->getJson("/api/academico/solicitudes/$sid")->assertStatus(404);
        $this->withHeaders($this->headers($ajeno))->postJson("/api/academico/solicitudes/$sid/decisiones", ['estado' => 'observada', 'version' => 1, 'motivo' => 'x prueba'])->assertStatus(404);
        $this->withHeaders($this->headers($ajeno))->getJson('/api/academico/solicitudes')->assertJsonPath('meta.total', 0);

        $det = $this->withHeaders($hg)->getJson("/api/academico/solicitudes/$sid")->assertOk()->json('data');
        $anexo = $det['revisiones'][0]['anexos'][0]['id_anexo'];
        $this->withHeaders($hg)->get("/api/academico/solicitudes/$sid/anexos/$anexo")->assertOk();
        $this->withHeaders($this->headers($ajeno))->get("/api/academico/solicitudes/$sid/anexos/$anexo")->assertStatus(404);

        // Pago pendiente de validar impide aceptar.
        $this->withHeaders($hg)->postJson("/api/academico/solicitudes/$sid/decisiones", ['estado' => 'aceptada', 'version' => 1, 'fecha_ingreso' => '2026-03-10'])->assertStatus(409)->assertJsonPath('code', 'PAGO_NO_VALIDADO');
        // Observar → token → corregir (cola simulada para leer el secreto aún cifrado).
        Queue::fake();
        $this->withHeaders($hg)->postJson("/api/academico/solicitudes/$sid/decisiones", ['estado' => 'observada', 'version' => 1, 'motivo' => 'La fotografía no es clara'])->assertOk();
        $entrega = EntregaAcceso::query()->where('tipo', 'observacion')->latest('id_entrega')->first();
        $token = Crypt::decryptString($entrega->secreto_cifrado);
        $this->withHeaders($h)->getJson("/api/academico/mis-solicitudes/$sid")->assertJsonPath('data.observacion', 'La fotografía no es clara');
        $this->postJson('/api/publico/correcciones', ['token' => 'invalido'])->assertStatus(409)->assertJsonPath('code', 'CORRECCION_INVALIDA');
        $this->post('/api/publico/correcciones', ['token' => $token, 'datos' => ['telefono_celular' => '7777-0000'], 'archivos' => ['fotografia' => $this->foto()]], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.revision', 2);
        $this->postJson('/api/publico/correcciones', ['token' => $token])->assertStatus(409);

        // Validar pago y aceptar.
        $det = $this->withHeaders($hg)->getJson("/api/academico/solicitudes/$sid")->json('data');
        $this->withHeaders($hg)->postJson("/api/academico/solicitudes/$sid/pago/revisiones", ['estado' => 'observado', 'version' => $det['pago']['version']])->assertStatus(422);
        $this->withHeaders($hg)->postJson("/api/academico/solicitudes/$sid/pago/revisiones", ['estado' => 'validado', 'version' => $det['pago']['version']])->assertOk();
        $r = $this->withHeaders($hg)->postJson("/api/academico/solicitudes/$sid/decisiones", ['estado' => 'aceptada', 'version' => $det['version'], 'fecha_ingreso' => '2026-03-10'])->assertOk();
        $this->assertSame('SH202601', $r->json('data.carnet'));
        $this->withHeaders($hg)->postJson("/api/academico/solicitudes/$sid/decisiones", ['estado' => 'aceptada', 'version' => $det['version'], 'fecha_ingreso' => '2026-03-10'])->assertOk()->assertJsonPath('data.id_expediente', $r->json('data.id_expediente'));
        $this->assertDatabaseCount('expediente', 1);

        // La cuenta conserva contraseña, adopta carnet y perfil estudiante; login por carnet y correo.
        $u->refresh();
        $this->assertSame('sh202601', $u->username);
        $this->assertDatabaseHas('auth_usuario_perfil', ['id_usuario' => $u->id_usuario, 'codigo_perfil' => 'academico_estudiante']);
        $this->postJson('/api/auth/login', ['identifier' => 'SH202601', 'password' => 'Secreta123'])->assertOk();
        $this->postJson('/api/auth/login', ['identifier' => 'maria@aspirante.test', 'password' => 'Secreta123'])->assertOk();
        $this->assertDatabaseHas('entrega_acceso', ['id_usuario' => $u->id_usuario, 'tipo' => 'carnet_asignado']);
    }

    public function test_est01_carnet_reglas_correlativos_y_colisiones(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $svc = app(IdentidadAccesoService::class);
        $this->assertSame(['SH', 'iniciales_apellidos'], IdentidadAccesoService::prefijo('Sibrián', 'Hernández'));
        $this->assertSame(['AX', 'segundo_apellido_ausente_x'], IdentidadAccesoService::prefijo('Álvarez', null));
        $this->assertSame(['NO', 'iniciales_apellidos'], IdentidadAccesoService::prefijo('Ñuñez', 'Ortiz'));

        DB::transaction(function () use ($svc) {
            $this->assertSame('SH202601', $svc->reservarCarnet('Sibrian', 'Hernandez')['carnet']);
            $this->assertSame('SH202602', $svc->reservarCarnet('Sosa', 'Huezo')['carnet']);
        });
        // Colisión con identificador previo consume el siguiente.
        $u = $this->usuario([], 'sh202603@x.test');
        $this->assertSame('SH202604', DB::transaction(fn () => $svc->reservarCarnet('S', 'H')['carnet']));
        // 99 → 100.
        CarnetCorrelativo::query()->where('prefijo', 'SH')->update(['ultimo' => 99]);
        $this->assertSame('SH2026100', DB::transaction(fn () => $svc->reservarCarnet('S', 'H')['carnet']));
        // Cambio de año en la zona institucional: 2026-12-31 22:00 local = 2027-01-01 04:00 UTC.
        Carbon::setTestNow('2027-01-01 04:00:00');
        $this->assertSame('SH2026101', DB::transaction(fn () => $svc->reservarCarnet('S', 'H', CarbonImmutable::now('UTC'))['carnet']), 'En la zona local sigue siendo 2026');
        Carbon::setTestNow('2027-01-01 07:00:00');
        $this->assertSame('SH202701', DB::transaction(fn () => $svc->reservarCarnet('S', 'H')['carnet']));
    }

    public function test_est01_cuenta_temporal_restringida_cambio_inicial_y_reenvio(): void
    {
        Mail::fake();
        $e = $this->escenario('DIR');
        $gestor = $this->gestorDe($e['carrera'], ['academico_gestor_expediente']);
        $r = $this->withHeaders($this->headers($gestor))->postJson('/api/academico/expedientes', [
            'nombres' => 'Luis', 'primer_apellido' => 'Pérez', 'segundo_apellido' => null, 'email' => 'luis@nuevo.test', 'id_carrera' => $e['carrera']->id_carrera, 'fecha_ingreso' => '2026-03-01',
        ])->assertStatus(201);
        $this->assertSame('PX202601', $r->json('data.carnet'));
        $u = AuthUsuario::where('email', 'luis@nuevo.test')->first();
        $this->assertTrue($u->password_cambio_requerido);
        $entrega = EntregaAcceso::query()->where('id_usuario', $u->id_usuario)->where('tipo', 'credenciales_nuevas')->first();
        $this->assertNotNull($entrega);
        // Colisión de correo con cuenta existente.
        $this->withHeaders($this->headers($gestor))->postJson('/api/academico/expedientes', ['nombres' => 'X', 'primer_apellido' => 'Y', 'email' => 'luis@nuevo.test', 'id_carrera' => $e['carrera']->id_carrera, 'fecha_ingreso' => '2026-03-01'])->assertStatus(409)->assertJsonPath('code', 'IDENTIFICADOR_OCUPADO');

        // Reenvío genera nueva temporal; la anterior deja de servir.
        $u->update(['password' => 'Temporal-1']);
        $this->withHeaders($this->headers($gestor))->postJson("/api/academico/expedientes/{$r->json('data.id_expediente')}/acceso/reenvios")->assertOk();
        $this->postJson('/api/auth/login', ['identifier' => 'PX202601', 'password' => 'Temporal-1'])->assertStatus(401);
        $u->refresh()->update(['password' => 'Temporal-2']);

        $login = $this->postJson('/api/auth/login', ['identifier' => 'px202601', 'password' => 'Temporal-2'])->assertOk();
        $hh = ['Authorization' => 'Bearer '.$login->json('access_token')];
        $this->withHeaders($hh)->getJson('/api/academico/mi-perfil')->assertStatus(403)->assertJsonPath('code', 'CAMBIO_PASSWORD_INICIAL_PENDIENTE');
        $this->withHeaders($hh)->getJson('/api/cuenta')->assertOk()->assertJsonPath('password_change_required', true);
        $this->withHeaders($hh)->postJson('/api/auth/change-password', ['current_password' => 'Temporal-2', 'new_password' => 'Nueva-123', 'password_confirmation' => 'Nueva-123'])->assertOk();
        $this->withHeaders($hh)->getJson('/api/academico/mi-perfil')->assertOk()->assertJsonPath('data.carnet', 'PX202601');

        // Temporal vencida.
        $u->refresh()->forceFill(['password_cambio_requerido' => true, 'password_temporal_expira_en' => now()->subHour()])->save();
        $this->withHeaders($hh)->getJson('/api/academico/mi-perfil')->assertStatus(403)->assertJsonPath('code', 'ACCESO_TEMPORAL_VENCIDO');
    }
}
