<?php

namespace Tests\Feature\Academico;

use App\Models\Academico\AsignacionDocente;
use App\Models\Academico\Facultad;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\Materia;
use App\Models\Academico\Sede;
use App\Services\Academico\CalendarioService;
use App\Services\Academico\CatalogoService;
use App\Services\Academico\PensumService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CAR-01, CAR-02 y CAR-03.
 */
class GestionCarrerasTest extends TestCase
{
    use AcademicoHelpers;

    public function test_car01_publicacion_invalida_conflicto_version_y_alcance(): void
    {
        $esc = $this->escenario('C1');
        $gestor = $this->gestorDe($esc['carrera']);
        $h = $this->headers($gestor);
        $id = $esc['carrera']->id_carrera;

        $c = $this->withHeaders($h)->postJson("/api/academico/carreras/$id/ciclos", ['codigo' => 'CII', 'nombre' => 'Ciclo II', 'calendario' => [
            'inicio' => '2026-08-01 00:00', 'fin' => '2026-12-15 00:00',
            'ventanas' => ['inscripcion' => ['inicio' => '2026-08-10 00:00', 'fin_regular' => '2026-08-05 00:00'], 'notas' => ['inicio' => '2026-08-01 00:00', 'fin_regular' => '2026-12-10 00:00']],
        ]])->assertStatus(201)->json('data');
        $this->withHeaders($h)->postJson("/api/academico/ciclos/{$c['id_ciclo_carrera']}/transiciones", ['estado' => 'publicado', 'version' => $c['version']])->assertStatus(422);

        $c = $this->withHeaders($h)->patchJson("/api/academico/ciclos/{$c['id_ciclo_carrera']}", ['version' => $c['version'], 'calendario' => ['ventanas' => ['inscripcion' => ['fin_regular' => '2026-08-20 00:00']]]])->assertOk()->json('data');
        $this->withHeaders($h)->patchJson("/api/academico/ciclos/{$c['id_ciclo_carrera']}", ['version' => $c['version'] - 1, 'nombre' => 'X'])->assertStatus(409);
        $this->withHeaders($h)->postJson("/api/academico/ciclos/{$c['id_ciclo_carrera']}/transiciones", ['estado' => 'publicado', 'version' => $c['version']])->assertOk()->assertJsonPath('data.estado', 'publicado');
        $this->withHeaders($h)->postJson("/api/academico/ciclos/{$c['id_ciclo_carrera']}/transiciones", ['estado' => 'borrador', 'version' => $c['version'] + 1])->assertStatus(422);

        // Ajuste sobre ciclo publicado exige motivo y deja historial.
        $pub = $this->withHeaders($h)->getJson("/api/academico/carreras/$id/ciclos/{$c['id_ciclo_carrera']}")->json('data');
        $this->withHeaders($h)->patchJson("/api/academico/ciclos/{$c['id_ciclo_carrera']}", ['version' => $pub['version'], 'calendario' => ['ventanas' => ['notas' => ['fin_gracia' => '2026-12-12 00:00']]]])->assertStatus(422);
        $this->withHeaders($h)->patchJson("/api/academico/ciclos/{$c['id_ciclo_carrera']}", ['version' => $pub['version'], 'motivo' => 'Gracia de notas', 'calendario' => ['ventanas' => ['notas' => ['fin_gracia' => '2026-12-12 00:00']]]])
            ->assertOk()->assertJsonPath('data.ajustes.0.motivo', 'Gracia de notas');

        // Otro gestor no accede.
        $otro = $this->gestorDe($this->carrera('OTRA'));
        $this->withHeaders($this->headers($otro))->getJson("/api/academico/carreras/$id/ciclos/{$c['id_ciclo_carrera']}")->assertStatus(404);
        $this->withHeaders($this->headers($otro))->patchJson("/api/academico/ciclos/{$c['id_ciclo_carrera']}", ['version' => 1])->assertStatus(404);
    }

    public function test_car01_servicio_unico_de_plazos_en_fronteras_y_cierre(): void
    {
        $esc = $this->escenario('C2');
        $svc = app(CalendarioService::class);
        $zona = 'America/El_Salvador';
        $ini = CarbonImmutable::parse('2026-03-01 00:00', $zona);
        $this->assertSame('anticipado', $svc->estadoVentana($esc['ciclo'], 'inscripcion', $ini->subSecond())['estado']);
        $this->assertSame('regular', $svc->estadoVentana($esc['ciclo'], 'inscripcion', $ini)['estado']);
        $this->assertSame('gracia', $svc->estadoVentana($esc['ciclo'], 'inscripcion', CarbonImmutable::parse('2026-03-20 00:00', $zona))['estado']);
        $this->assertSame('cerrado', $svc->estadoVentana($esc['ciclo'], 'inscripcion', CarbonImmutable::parse('2026-03-22 00:00', $zona))['estado']);

        $h = $this->headers($this->gestorDe($esc['carrera']));
        $this->withHeaders($h)->postJson("/api/academico/ciclos/{$esc['ciclo']->id_ciclo_carrera}/transiciones", ['estado' => 'cerrado', 'version' => $esc['ciclo']->version])->assertStatus(409)->assertJsonPath('code', 'CIERRE_NO_PERMITIDO');
    }

    public function test_car01_configuracion_incompleta_bloquea_publicacion(): void
    {
        $c = $this->carrera('SIN');
        $m = Materia::create(['codigo' => 'Z1', 'nombre' => 'Z']);
        $svc = app(PensumService::class);
        $p = $svc->crear($c, 'v1', null, '537b8b56-341c-4322-869a-de00d11cf10e');
        $svc->actualizarBorrador($p, ['version' => 1, 'vigente_desde' => '2026-01-01', 'materias' => [['id_materia' => $m->id_materia, 'nivel' => 1, 'creditos' => 3]]], '537b8b56-341c-4322-869a-de00d11cf10e');
        $svc->transicion($p->fresh(), 'publicado', 2, '537b8b56-341c-4322-869a-de00d11cf10e');
        $h = $this->headers($this->gestorDe($c));
        $ciclo = $this->withHeaders($h)->postJson("/api/academico/carreras/{$c->id_carrera}/ciclos", ['codigo' => 'X', 'nombre' => 'X', 'calendario' => [
            'inicio' => '2026-02-01 00:00', 'fin' => '2026-07-01 00:00',
            'ventanas' => ['inscripcion' => ['inicio' => '2026-02-01 00:00', 'fin_regular' => '2026-02-10 00:00'], 'notas' => ['inicio' => '2026-02-01 00:00', 'fin_regular' => '2026-06-10 00:00']],
        ]])->json('data');
        $this->withHeaders($h)->postJson("/api/academico/ciclos/{$ciclo['id_ciclo_carrera']}/transiciones", ['estado' => 'publicado', 'version' => $ciclo['version']])
            ->assertStatus(409)->assertJsonPath('code', 'CONFIGURACION_INCOMPLETA')->assertJsonPath('meta.faltantes.0', 'calificaciones.id_escala_vigente');
    }

    public function test_car02_publicacion_grupo_cupo_cancelacion_y_reasignacion(): void
    {
        $esc = $this->escenario('C3');
        $h = $this->headers($this->gestorDe($esc['carrera']));
        $ciclo = $esc['ciclo']->id_ciclo_carrera;

        // Materia ajena a los planes de la carrera.
        $ajena = Materia::create(['codigo' => 'AJENA', 'nombre' => 'Ajena']);
        $this->withHeaders($h)->postJson("/api/academico/ciclos/$ciclo/ofertas", ['id_materia' => $ajena->id_materia])->assertStatus(409)->assertJsonPath('code', 'MATERIA_NO_OFRECIBLE');

        // Docente inactivo impide publicar.
        $oferta = $esc['grupos']['M1']->id_oferta_materia;
        $g = $this->withHeaders($h)->postJson("/api/academico/ofertas/$oferta/grupos", ['codigo' => 'G2', 'cupo' => 2, 'horarios' => [['dia' => 3, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']], 'id_docente' => $esc['docente']->id_docente])->assertStatus(201)->json('data');
        $esc['docente']->update(['estado' => 'inactivo']);
        $this->withHeaders($h)->patchJson("/api/academico/grupos/{$g['id_grupo']}", ['version' => 1, 'estado' => 'publicado'])->assertStatus(409)->assertJsonPath('code', 'DOCENTE_INACTIVO');
        $esc['docente']->update(['estado' => 'activo']);
        $this->withHeaders($h)->postJson("/api/academico/ofertas/$oferta/grupos", ['codigo' => 'G3', 'horarios' => [['dia' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00'], ['dia' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '11:00']]])->assertStatus(422);

        // Cupo inferior a inscritos y cancelación con inscripciones dejan estado intacto.
        [, $exp] = $this->estudianteEn($esc);
        [, $exp2] = $this->estudianteEn($esc, 'Gomez');
        foreach ([$exp, $exp2] as $e) {
            Inscripcion::create(['id_expediente' => $e->id_expediente, 'id_ciclo_carrera' => $ciclo, 'id_materia' => $esc['m']['M1']->id_materia, 'id_grupo' => $esc['grupos']['M1']->id_grupo, 'estado' => 'activa', 'inscrito_en' => now()]);
        }
        $g1 = $esc['grupos']['M1']->fresh();
        $this->withHeaders($h)->patchJson("/api/academico/grupos/{$g1->id_grupo}", ['version' => $g1->version, 'cupo' => 1])->assertStatus(409)->assertJsonPath('code', 'CUPO_INFERIOR_A_INSCRITOS');
        $this->withHeaders($h)->patchJson("/api/academico/grupos/{$g1->id_grupo}", ['version' => $g1->version, 'estado' => 'cancelado'])->assertStatus(409)->assertJsonPath('code', 'GRUPO_CON_INSCRIPCIONES');
        $this->assertSame(30, (int) $g1->fresh()->cupo);
        $this->assertSame('publicado', $g1->fresh()->estado);

        // Reasignación conserva historial.
        $c2 = $this->usuario();
        [$d2] = app(CatalogoService::class)->crearDocente(['id_usuario' => $c2->id_usuario, 'codigo' => 'D2', 'nombre_academico' => 'Segundo'], $esc['admin']);
        $this->withHeaders($h)->patchJson("/api/academico/grupos/{$g1->id_grupo}", ['version' => $g1->version, 'id_docente' => $d2->id_docente])->assertOk()->assertJsonPath('data.docente.codigo', 'D2');
        $this->assertDatabaseHas('asignacion_docente', ['id_grupo' => $g1->id_grupo, 'id_docente' => $esc['docente']->id_docente]);
        $this->assertSame(1, AsignacionDocente::query()->where('id_grupo', $g1->id_grupo)->whereNull('hasta')->count());

        // Horario que choca con otra inscripción del estudiante (M3 lunes 10-12).
        Inscripcion::create(['id_expediente' => $exp->id_expediente, 'id_ciclo_carrera' => $ciclo, 'id_materia' => $esc['m']['M3']->id_materia, 'id_grupo' => $esc['grupos']['M3']->id_grupo, 'estado' => 'activa', 'inscrito_en' => now()]);
        $this->withHeaders($h)->patchJson("/api/academico/grupos/{$g1->id_grupo}", ['version' => $g1->version + 1, 'horarios' => [['dia' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '11:00']]])->assertStatus(409)->assertJsonPath('code', 'HORARIO_AFECTA_INSCRIPCIONES');
        $this->withHeaders($h)->patchJson("/api/academico/grupos/{$g1->id_grupo}", ['version' => $g1->version + 1, 'horarios' => [['dia' => 1, 'hora_inicio' => '06:00', 'hora_fin' => '10:00']]])->assertOk();
    }

    public function test_car03_convocatoria_publicacion_versionado_y_cierre(): void
    {
        $esc = $this->escenario('C4');
        $sede = Sede::create(['codigo' => 'SS', 'nombre' => 'San Salvador']);
        $fac = Facultad::create(['codigo' => 'FIA', 'nombre' => 'Ingeniería']);
        $esc['carrera']->update(['id_facultad' => $fac->id_facultad]);
        $esc['carrera']->sedes()->sync([$sede->id_sede]);
        $h = $this->headers($this->gestorDe($esc['carrera']));
        $id = $esc['carrera']->id_carrera;

        $c = $this->withHeaders($h)->postJson("/api/academico/carreras/$id/convocatorias", [
            'codigo' => 'ADM-2026', 'titulo_publico' => 'Admisión 2026', 'inicio' => '2026-03-01 00:00', 'fin' => '2026-04-01 00:00',
            'ofertas' => [['id_sede' => $sede->id_sede, 'id_facultad' => $fac->id_facultad, 'modalidad_estudio' => 'presencial', 'modalidad_ingreso' => 'general']],
        ])->assertStatus(201)->json('data');
        $this->assertGreaterThan(5, count($c['requisitos']));
        $this->getJson('/api/publico/convocatorias')->assertOk()->assertJsonPath('meta.total', 0);

        $this->withHeaders($h)->patchJson("/api/academico/convocatorias/{$c['id_convocatoria']}", ['version' => 1, 'requisitos' => [['codigo' => 'x', 'tipo' => 'campo', 'nombre' => 'X', 'condicion' => ['campo' => 'inexistente', 'op' => 'eq', 'valor' => 1]]]])->assertStatus(422);
        $c = $this->withHeaders($h)->patchJson("/api/academico/convocatorias/{$c['id_convocatoria']}", ['version' => 1, 'estado' => 'publicada'])->assertOk()->json('data');
        $this->getJson('/api/publico/convocatorias')->assertOk()->assertJsonPath('meta.total', 1);

        // Editar requisitos tras publicar crea nueva versión.
        $c = $this->withHeaders($h)->patchJson("/api/academico/convocatorias/{$c['id_convocatoria']}", ['version' => $c['version'], 'requisitos' => [['codigo' => 'fotografia', 'tipo' => 'anexo', 'nombre' => 'Foto']]])->assertOk()->json('data');
        $this->assertSame(2, $c['version_requisitos']);
        $this->assertDatabaseHas('requisito_ingreso', ['id_convocatoria' => $c['id_convocatoria'], 'version_requisitos' => 1, 'codigo' => 'dui']);

        $this->withHeaders($h)->patchJson("/api/academico/convocatorias/{$c['id_convocatoria']}", ['version' => $c['version'], 'estado' => 'cerrada'])->assertOk()->assertJsonPath('data.abierta', false);
        $this->getJson('/api/publico/convocatorias')->assertJsonPath('meta.total', 0);
        $this->withHeaders($h)->getJson("/api/academico/convocatorias/{$c['id_convocatoria']}")->assertOk();
        Carbon::setTestNow();
    }
}
