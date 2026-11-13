<?php

namespace Tests\Feature\Academico;

use App\Models\Academico\Materia;
use App\Services\Academico\PensumService;
use Tests\TestCase;

/**
 * GEN-01, GEN-02 y GEN-03.
 */
class AdministracionGeneralTest extends TestCase
{
    use AcademicoHelpers;

    public function test_gen01_alta_docente_idempotente_conflicto_y_desactivacion(): void
    {
        $h = $this->headers($this->admin());
        $cuenta = $this->usuario();
        $body = ['id_usuario' => $cuenta->id_usuario, 'codigo' => ' doc-01 ', 'nombre_academico' => 'Ing. Prueba'];
        $r = $this->withHeaders($h)->postJson('/api/academico/docentes', $body)->assertStatus(201)->assertJsonPath('data.codigo', 'DOC-01');
        $this->assertDatabaseHas('auth_usuario_perfil', ['id_usuario' => $cuenta->id_usuario, 'codigo_perfil' => 'academico_docente']);
        $this->withHeaders($h)->postJson('/api/academico/docentes', $body)->assertOk();
        $this->withHeaders($h)->postJson('/api/academico/docentes', ['nombre_academico' => 'Otro'] + $body)->assertStatus(409)->assertJsonPath('code', 'DOCENTE_EXISTENTE');

        $id = $r->json('data.id_docente');
        $this->withHeaders($h)->patchJson("/api/academico/docentes/$id", ['version' => 1, 'estado' => 'inactivo'])->assertOk()->assertJsonPath('data.estado', 'inactivo');
        $this->withHeaders($h)->patchJson("/api/academico/docentes/$id", ['version' => 1, 'estado' => 'activo'])->assertStatus(409);
        $this->withHeaders($h)->getJson('/api/academico/docentes?estado=inactivo&buscar=doc')->assertOk()->assertJsonPath('meta.total', 1);

        $this->withHeaders($this->headers($this->usuario(['academico_estudiante'])))->getJson('/api/academico/docentes')->assertStatus(403);
    }

    public function test_gen02_codigos_normalizados_archivado_y_gestores_aislados(): void
    {
        $h = $this->headers($this->admin());
        $this->withHeaders($h)->postJson('/api/academico/carreras', ['codigo' => 'ing-civ', 'nombre' => 'Ingeniería Civil'])->assertStatus(201)->assertJsonPath('data.codigo', 'ING-CIV');
        $this->withHeaders($h)->postJson('/api/academico/carreras', ['codigo' => ' ING-CIV ', 'nombre' => 'Dup'])->assertStatus(422);
        $this->withHeaders($h)->postJson('/api/academico/carreras', ['codigo' => 'X', 'nombre' => 'Y', 'campo' => 1])->assertStatus(422);

        $a = $this->carrera('A');
        $b = $this->carrera('B');
        $ga = $this->gestorDe($a);
        $gb = $this->gestorDe($b);
        $this->withHeaders($this->headers($ga))->getJson('/api/academico/mis-carreras-gestionadas')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.codigo', 'A');
        $this->withHeaders($this->headers($gb))->getJson('/api/academico/mis-carreras-gestionadas')->assertJsonPath('data.0.codigo', 'B');
        $this->withHeaders($this->headers($ga))->getJson('/api/academico/carreras/'.$b->id_carrera.'/convocatorias')->assertStatus(404);

        // Asignación exige perfil de gestor; revocar surte efecto sin reasignar perfiles.
        $sinPerfil = $this->usuario();
        $this->withHeaders($h)->putJson("/api/academico/carreras/{$a->id_carrera}/gestores/{$sinPerfil->id_usuario}")->assertStatus(422);
        $this->withHeaders($h)->deleteJson("/api/academico/carreras/{$a->id_carrera}/gestores/{$ga->id_usuario}")->assertOk();
        $this->withHeaders($this->headers($ga))->getJson('/api/academico/carreras/'.$a->id_carrera.'/convocatorias')->assertStatus(404);
        $this->assertDatabaseHas('auth_usuario_perfil', ['id_usuario' => $ga->id_usuario, 'codigo_perfil' => 'academico_gestor_carrera']);
        $this->withHeaders($h)->putJson("/api/academico/carreras/{$a->id_carrera}/gestores/{$ga->id_usuario}")->assertStatus(201);
        $this->withHeaders($h)->putJson("/api/academico/carreras/{$a->id_carrera}/gestores/{$ga->id_usuario}")->assertOk();

        // Archivar respeta operaciones vigentes.
        $esc = $this->escenario('ARC');
        $h = $this->headers($this->admin());
        $this->withHeaders($h)->patchJson('/api/academico/carreras/'.$esc['carrera']->id_carrera, ['version' => 1, 'estado' => 'archivada'])->assertStatus(409)->assertJsonPath('code', 'CARRERA_CON_OPERACION_VIGENTE');
        $this->withHeaders($h)->patchJson('/api/academico/materias/'.$esc['m']['M1']->id_materia, ['version' => 1, 'estado' => 'archivada'])->assertStatus(409);
        $this->withHeaders($h)->patchJson('/api/academico/materias/'.$esc['m']['M1']->id_materia, ['version' => 1, 'codigo' => 'NUEVO'])->assertStatus(422);
    }

    public function test_gen03_validaciones_publicacion_unica_y_clonado(): void
    {
        $h = $this->headers($this->admin());
        $c = $this->carrera('PEN');
        [$a, $b, $x] = [Materia::create(['codigo' => 'A', 'nombre' => 'A']), Materia::create(['codigo' => 'B', 'nombre' => 'B']), Materia::create(['codigo' => 'C', 'nombre' => 'C'])];
        $p = $this->withHeaders($h)->postJson("/api/academico/carreras/{$c->id_carrera}/pensums", ['version_curricular' => '2020'])->assertStatus(201)->json('data');

        $mat = [['id_materia' => $a->id_materia, 'nivel' => 1, 'creditos' => 4], ['id_materia' => $b->id_materia, 'nivel' => 2, 'creditos' => 4]];
        $this->withHeaders($h)->patchJson("/api/academico/pensums/{$p['id_pensum']}", ['version' => 1, 'materias' => [$mat[0], $mat[0]]])->assertStatus(422);
        $this->withHeaders($h)->patchJson("/api/academico/pensums/{$p['id_pensum']}", ['version' => 1, 'materias' => $mat, 'requisitos' => [
            ['id_materia' => $a->id_materia, 'id_materia_requisito' => $b->id_materia], ['id_materia' => $b->id_materia, 'id_materia_requisito' => $a->id_materia],
        ]])->assertStatus(422);
        $this->withHeaders($h)->patchJson("/api/academico/pensums/{$p['id_pensum']}", ['version' => 1, 'materias' => $mat, 'requisitos' => [['id_materia' => $a->id_materia, 'id_materia_requisito' => $a->id_materia]]])->assertStatus(422);
        $this->withHeaders($h)->patchJson("/api/academico/pensums/{$p['id_pensum']}", ['version' => 1, 'materias' => $mat, 'equivalencias' => [
            ['id_materia_origen' => $x->id_materia, 'id_materia_destino' => $a->id_materia], ['id_materia_origen' => $x->id_materia, 'id_materia_destino' => $b->id_materia],
        ]])->assertStatus(422);

        $this->withHeaders($h)->patchJson("/api/academico/pensums/{$p['id_pensum']}", ['version' => 1, 'vigente_desde' => '2020-01-01', 'materias' => $mat, 'requisitos' => [['id_materia' => $b->id_materia, 'id_materia_requisito' => $a->id_materia]]])->assertOk();
        $this->withHeaders($h)->postJson("/api/academico/pensums/{$p['id_pensum']}/transiciones", ['estado' => 'publicado', 'version' => 2])->assertOk()->assertJsonPath('data.estado', 'publicado');
        $this->withHeaders($h)->postJson("/api/academico/pensums/{$p['id_pensum']}/transiciones", ['estado' => 'publicado', 'version' => 2])->assertOk();
        $this->withHeaders($h)->patchJson("/api/academico/pensums/{$p['id_pensum']}", ['version' => 3, 'materias' => []])->assertStatus(409);

        // Clonar y publicar: cierra intervalo previo; una sola versión aplicable por fecha.
        $n = $this->withHeaders($h)->postJson("/api/academico/carreras/{$c->id_carrera}/pensums", ['version_curricular' => '2026', 'id_pensum_origen' => $p['id_pensum']])->assertStatus(201)->json('data');
        $this->assertCount(2, $n['materias']);
        $this->assertCount(1, $n['requisitos']);
        $this->assertSame('borrador', $n['estado']);
        $this->withHeaders($h)->patchJson("/api/academico/pensums/{$n['id_pensum']}", ['version' => 1, 'vigente_desde' => '2026-01-01'])->assertOk();
        $this->withHeaders($h)->postJson("/api/academico/pensums/{$n['id_pensum']}/transiciones", ['estado' => 'publicado', 'version' => 2])->assertOk();
        $svc = app(PensumService::class);
        $this->assertSame($p['id_pensum'], $svc->aplicable($c->id_carrera, '2025-12-31')->id_pensum);
        $this->assertSame($n['id_pensum'], $svc->aplicable($c->id_carrera, '2026-01-01')->id_pensum);
        $this->withHeaders($h)->getJson("/api/academico/pensums/{$p['id_pensum']}")->assertJsonPath('data.vigente_hasta', '2026-01-01')->assertJsonCount(2, 'data.materias');

        // Vigencia solapada con versión cerrada se rechaza.
        $s = $this->withHeaders($h)->postJson("/api/academico/carreras/{$c->id_carrera}/pensums", ['version_curricular' => '2021', 'id_pensum_origen' => $p['id_pensum']])->json('data');
        $this->withHeaders($h)->patchJson("/api/academico/pensums/{$s['id_pensum']}", ['version' => 1, 'vigente_desde' => '2021-01-01', 'vigente_hasta' => '2022-01-01'])->assertOk();
        $this->withHeaders($h)->postJson("/api/academico/pensums/{$s['id_pensum']}/transiciones", ['estado' => 'publicado', 'version' => 2])->assertStatus(422);
    }
}
