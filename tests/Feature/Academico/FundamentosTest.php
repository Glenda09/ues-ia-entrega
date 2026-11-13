<?php

namespace Tests\Feature\Academico;

use App\Models\Academico\Auditoria;
use App\Models\Seguridad\SeguridadPerfil;
use App\Models\Seguridad\SeguridadPerfilAccion;
use App\Models\Seguridad\SeguridadPermiso;
use App\Services\Academico\AuditoriaService;
use App\Support\Academico\Ventana;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * BASE-01 y BASE-02.
 */
class FundamentosTest extends TestCase
{
    use AcademicoHelpers;

    public function test_seeder_academico_es_idempotente_y_conserva_asignaciones(): void
    {
        $u = $this->usuario(['academico_admin']);
        $perfiles = SeguridadPerfil::count();
        $accionesPerfil = SeguridadPerfilAccion::count();
        $permisos = SeguridadPermiso::count();

        $this->artisan('db:seed', ['--class' => 'AcademicoRbacSeeder']);
        $this->artisan('db:seed', ['--class' => 'AcademicoRbacSeeder']);

        $this->assertSame($perfiles, SeguridadPerfil::count());
        $this->assertSame($accionesPerfil, SeguridadPerfilAccion::count());
        $this->assertSame($permisos, SeguridadPermiso::count());
        $this->assertDatabaseHas('auth_usuario_perfil', ['id_usuario' => $u->id_usuario, 'codigo_perfil' => 'academico_admin']);
        foreach (['academico_admin', 'academico_gestor_carrera', 'academico_gestor_expediente', 'academico_docente', 'academico_estudiante', 'academico_aspirante'] as $codigo) {
            $this->assertDatabaseHas('seguridad_perfil', ['codigo' => $codigo]);
        }
        // Usuario base conserva acceso.
        $this->postJson(route('public.auth.login'), ['identifier' => 'admin@example.com', 'password' => 'password123'])->assertOk();
    }

    public function test_reloj_y_ventanas_en_fronteras(): void
    {
        $zona = 'America/El_Salvador';
        $inicio = CarbonImmutable::parse('2026-01-10 08:00', $zona)->utc();
        $finReg = CarbonImmutable::parse('2026-01-20 00:00', $zona)->utc();
        $finGra = CarbonImmutable::parse('2026-01-22 00:00', $zona)->utc();

        $this->assertSame('2026-01-10 14:00', $inicio->format('Y-m-d H:i'), 'Conversión a UTC desde la zona');
        $this->assertSame(Ventana::ANTICIPADO, Ventana::evaluar($inicio, $finReg, $finGra, true, $inicio->subSecond()));
        $this->assertSame(Ventana::REGULAR, Ventana::evaluar($inicio, $finReg, $finGra, true, $inicio));
        $this->assertSame(Ventana::REGULAR, Ventana::evaluar($inicio, $finReg, $finGra, true, $finReg->subSecond()));
        $this->assertSame(Ventana::GRACIA, Ventana::evaluar($inicio, $finReg, $finGra, true, $finReg));
        $this->assertSame(Ventana::CERRADO, Ventana::evaluar($inicio, $finReg, $finGra, true, $finGra));
        $this->assertSame(Ventana::CERRADO, Ventana::evaluar($inicio, $finReg, null, true, $finReg));
        $this->assertSame(Ventana::CERRADO, Ventana::evaluar($inicio, $finReg, $finGra, false, $inicio), 'Sin publicar');
        $this->assertSame(Ventana::CERRADO, Ventana::evaluar(null, $finReg, $finGra, true, $inicio), 'Plazo faltante');

        $this->assertTrue(Ventana::puedeOperar(Ventana::REGULAR, [], []));
        $this->assertFalse(Ventana::puedeOperar(Ventana::GRACIA, ['x'], []), 'Lista vacía no habilita');
        $this->assertTrue(Ventana::puedeOperar(Ventana::GRACIA, ['x'], ['x']));
        $this->assertFalse(Ventana::puedeOperar(Ventana::GRACIA, ['y'], ['x']));
    }

    public function test_acceso_diferencia_401_403_y_404_fuera_de_alcance(): void
    {
        $this->getJson('/api/academico/carreras/1')->assertStatus(401);

        $sinPermiso = $this->usuario(['academico_estudiante']);
        $this->withHeaders($this->headers($sinPermiso))->getJson('/api/academico/configuracion')->assertStatus(403);

        $a = $this->carrera('A');
        $b = $this->carrera('B');
        $gestor = $this->gestorDe($a);
        $this->withHeaders($this->headers($gestor))->getJson('/api/academico/carreras/'.$a->id_carrera.'/ciclos')->assertOk();
        $this->withHeaders($this->headers($gestor))->getJson('/api/academico/carreras/'.$b->id_carrera.'/ciclos')->assertStatus(404);
    }

    public function test_auditoria_conserva_metadatos_sin_payload(): void
    {
        $a = app(AuditoriaService::class)->registrar('537b8b56-341c-4322-869a-de00d11cf10e', 'prueba.operacion', 'entidad', 5, 'motivo', ['revision' => 2]);
        $this->assertNotNull($a->instante);
        $this->assertSame(['revision' => 2], Auditoria::find($a->id_auditoria)->metadatos);
    }

    public function test_configuracion_guardar_versionar_y_conflicto(): void
    {
        $admin = $this->admin();
        $h = $this->headers($admin);
        $actual = $this->withHeaders($h)->getJson('/api/academico/configuracion')->assertOk()->json('data');
        $this->assertSame('America/El_Salvador', $actual['valores']['calendario']['zona']);

        $this->withHeaders($h)->putJson('/api/academico/configuracion', [
            'version' => $actual['version'],
            'motivo' => 'Calendario 2026',
            'grupos' => ['calendario' => ['ciclo_inicio' => '2026-02-01', 'ciclo_fin' => '2026-07-01', 'gracia_horas_notas' => 48]],
        ])->assertOk()->assertJsonPath('data.version', $actual['version'] + 1)
            ->assertJsonPath('data.valores.calendario.gracia_horas_notas', 48);

        $this->withHeaders($h)->putJson('/api/academico/configuracion', [
            'version' => $actual['version'],
            'motivo' => 'Desactualizado',
            'grupos' => ['inscripcion' => ['cupo_default' => 40]],
        ])->assertStatus(409)->assertJsonPath('code', 'VERSION_CONFLICTO');

        $this->withHeaders($h)->getJson('/api/academico/configuracion/revisiones')->assertOk()->assertJsonPath('data.0.motivo', 'Calendario 2026');
    }

    public function test_configuracion_invalida_produce_422_sin_cambios(): void
    {
        $h = $this->headers($this->admin());
        $v = $this->withHeaders($h)->getJson('/api/academico/configuracion')->json('data.version');

        $casos = [
            ['calendario' => ['zona' => 'Marte/Olympus']],
            ['calendario' => ['notas_inicio' => '2026-05-10 00:00', 'notas_fin' => '2026-05-01 00:00']],
            ['calendario' => ['gracia_horas_inscripcion' => -1]],
            ['inscripcion' => ['campo_extra' => 1]],
        ];
        foreach ($casos as $grupos) {
            $this->withHeaders($h)->putJson('/api/academico/configuracion', ['version' => $v, 'motivo' => 'x prueba', 'grupos' => $grupos])->assertStatus(422);
        }
        $this->withHeaders($h)->getJson('/api/academico/configuracion')->assertJsonPath('data.version', $v);

        $this->withHeaders($h)->postJson('/api/academico/configuracion/escalas', [
            'codigo' => 'E', 'nombre' => 'Mala', 'minimo' => 10, 'maximo' => 0, 'umbral_aprobacion' => 6,
            'precision' => 2, 'redondeo' => 'half_up', 'ponderacion_total' => 100, 'ausencia' => 'bloquear',
        ])->assertStatus(422);

        $this->withHeaders($h)->postJson('/api/academico/configuracion/escalas', [
            'codigo' => 'E', 'nombre' => 'Escala prueba 0-10', 'minimo' => 0, 'maximo' => 10, 'umbral_aprobacion' => 6,
            'precision' => 2, 'redondeo' => 'half_up', 'ponderacion_total' => 100, 'ausencia' => 'bloquear',
        ])->assertStatus(201)->assertJsonPath('data.version_politica', 1);
    }
}
