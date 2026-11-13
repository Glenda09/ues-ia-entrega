<?php

namespace Tests\Feature\Academico;

use App\Models\Academico\ActaGrupo;
use App\Models\Academico\DocumentoExpediente;
use App\Models\Academico\Expediente;
use App\Models\Academico\ExpedienteEvento;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\InscripcionEvento;
use App\Models\Academico\ResultadoFinal;
use App\Models\App\AppPersonalizacion;
use App\Services\Academico\CalificacionService;
use App\Services\Academico\DocumentoService;
use App\Services\Academico\ExpedienteEstados;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * EXP-03, INS-01, INS-02, DOC-01, DOC-02, EST-02, EXP-04 y GEN-04.
 */
class TrayectoriaAcademicaTest extends TestCase
{
    use AcademicoHelpers;

    public function test_exp03_matriz_completa_de_estados(): void
    {
        $esc = $this->escenario('EST');
        [, $exp] = $this->estudianteEn($esc);
        $h = $this->headers($this->gestorDe($esc['carrera'], ['academico_gestor_expediente']));
        $estados = array_keys(ExpedienteEstados::ETIQUETAS);
        foreach ($estados as $origen) {
            foreach ($estados as $destino) {
                if ($origen === $destino) {
                    continue;
                }
                Expediente::whereKey($exp->id_expediente)->update(['estado' => $origen, 'version' => 1]);
                $r = $this->withHeaders($h)->postJson("/api/academico/expedientes/{$exp->id_expediente}/transiciones", ['estado' => $destino, 'motivo' => 'Prueba matriz', 'version' => 1]);
                if (ExpedienteEstados::permitida($origen, $destino)) {
                    $r->assertOk()->assertJsonPath('data.estado', $destino);
                } else {
                    $r->assertStatus(409)->assertJsonPath('code', 'TRANSICION_ESTUDIANTE_INVALIDA');
                }
            }
        }
        foreach ([['INACTIVO', 'EGRESADO'], ['INACTIVO', 'RETIRADO'], ['RETIRADO', 'EGRESADO']] as [$o, $d]) {
            $this->assertFalse(ExpedienteEstados::permitida($o, $d));
        }
        // Estado actual = reintento sin evento duplicado.
        Expediente::whereKey($exp->id_expediente)->update(['estado' => 'ACTIVO', 'version' => 1]);
        $antes = ExpedienteEvento::count();
        $this->withHeaders($h)->postJson("/api/academico/expedientes/{$exp->id_expediente}/transiciones", ['estado' => 'ACTIVO', 'motivo' => 'Repetido', 'version' => 1])->assertOk();
        $this->assertSame($antes, ExpedienteEvento::count());
        // Campos protegidos y alcance.
        $this->withHeaders($h)->patchJson("/api/academico/expedientes/{$exp->id_expediente}", ['version' => 1, 'carnet' => 'X'])->assertStatus(422);
        $this->withHeaders($h)->patchJson("/api/academico/expedientes/{$exp->id_expediente}", ['version' => 1, 'telefono' => '2222-0000'])->assertOk()->assertJsonPath('data.estudiante.telefono', '2222-0000');
        $ajeno = $this->headers($this->gestorDe($this->carrera('Z'), ['academico_gestor_expediente']));
        $this->withHeaders($ajeno)->getJson("/api/academico/expedientes/{$exp->id_expediente}")->assertStatus(404);
        $this->withHeaders($ajeno)->getJson('/api/academico/expedientes')->assertJsonPath('meta.total', 0);
        $this->withHeaders($h)->getJson("/api/academico/expedientes/{$exp->id_expediente}")->assertOk()->assertJsonCount(0, 'data.historial.finalizados');
    }

    public function test_ins_elegibilidad_confirmacion_idempotente_retiro_y_aislamiento(): void
    {
        $esc = $this->escenario('INS');
        [$u, $exp] = $this->estudianteEn($esc);
        [$u2, $exp2] = $this->estudianteEn($esc, 'Gomez');
        $h = $this->headers($u);
        $ciclo = $esc['ciclo']->id_ciclo_carrera;
        $q = "/api/academico/mi-oferta?expediente={$exp->id_expediente}&ciclo=$ciclo";

        $of = collect($this->withHeaders($h)->getJson($q)->assertOk()->json('data.materias'))->keyBy('codigo');
        $this->assertTrue($of['INS-M1']['puede_inscribir']);
        $this->assertSame('PRERREQUISITO_PENDIENTE', $of['INS-M2']['razones'][0]['code']);

        // Ajeno: 404 alterando ID.
        $this->withHeaders($h)->getJson("/api/academico/mi-oferta?expediente={$exp2->id_expediente}&ciclo=$ciclo")->assertStatus(404);

        $g1 = $esc['grupos']['M1']->id_grupo;
        $g3 = $esc['grupos']['M3']->id_grupo;
        $g2 = $esc['grupos']['M2']->id_grupo;
        // Conjunto con un conflicto no inscribe parcialmente.
        $this->withHeaders($h + ['Idempotency-Key' => 'a'])->postJson('/api/academico/mis-inscripciones', ['id_expediente' => $exp->id_expediente, 'id_ciclo' => $ciclo, 'grupos' => [$g1, $g2]])->assertStatus(409)->assertJsonPath('code', 'PRERREQUISITO_PENDIENTE');
        $this->assertSame(0, Inscripcion::count());

        $body = ['id_expediente' => $exp->id_expediente, 'id_ciclo' => $ciclo, 'grupos' => [$g1, $g3]];
        $this->withHeaders($h + ['Idempotency-Key' => 'b'])->postJson('/api/academico/mis-inscripciones', $body)->assertStatus(201)->assertJsonCount(2, 'data');
        $this->withHeaders($h + ['Idempotency-Key' => 'b'])->postJson('/api/academico/mis-inscripciones', $body)->assertOk();
        $this->withHeaders($h + ['Idempotency-Key' => 'b'])->postJson('/api/academico/mis-inscripciones', ['grupos' => [$g1]] + $body)->assertStatus(409)->assertJsonPath('code', 'IDEMPOTENCIA_CONFLICTO');
        $this->withHeaders($h + ['Idempotency-Key' => 'c'])->postJson('/api/academico/mis-inscripciones', $body)->assertStatus(201);
        $this->assertSame(2, Inscripcion::count());
        // Otro estudiante no reutiliza la clave ajena.
        $this->withHeaders($this->headers($u2) + ['Idempotency-Key' => 'b'])->postJson('/api/academico/mis-inscripciones', ['id_expediente' => $exp->id_expediente] + $body)->assertStatus(404);

        $of = collect($this->withHeaders($h)->getJson($q)->json('data.materias'))->keyBy('codigo');
        $this->assertSame('MATERIA_YA_INSCRITA', $of['INS-M1']['razones'][0]['code']);

        // Retiro repetido libera una sola plaza.
        $ins = Inscripcion::where('id_grupo', $g3)->first();
        $this->withHeaders($h)->postJson("/api/academico/mis-inscripciones/{$ins->id_inscripcion}/retiros", ['motivo' => 'Cambio de plan'])->assertOk()->assertJsonPath('data.estado', 'retirada');
        $this->withHeaders($h)->postJson("/api/academico/mis-inscripciones/{$ins->id_inscripcion}/retiros", ['motivo' => 'Cambio de plan'])->assertOk();
        $this->assertSame(1, InscripcionEvento::where('operacion', 'retiro')->count());
        $this->withHeaders($this->headers($u2))->postJson("/api/academico/mis-inscripciones/{$ins->id_inscripcion}/retiros", ['motivo' => 'x prueba'])->assertStatus(404);
        // Reinscripción conserva fila y registra evento.
        $this->withHeaders($h + ['Idempotency-Key' => 'd'])->postJson('/api/academico/mis-inscripciones', ['id_expediente' => $exp->id_expediente, 'id_ciclo' => $ciclo, 'grupos' => [$g3]])->assertStatus(201);
        $this->assertSame('activa', $ins->fresh()->estado);
        $this->assertSame(1, InscripcionEvento::where('operacion', 'reinscripcion')->count());

        // Estado no Activo impide inscribir.
        $exp2->update(['estado' => 'INACTIVO']);
        $this->withHeaders($this->headers($u2) + ['Idempotency-Key' => 'e'])->postJson('/api/academico/mis-inscripciones', ['id_expediente' => $exp2->id_expediente, 'id_ciclo' => $ciclo, 'grupos' => [$g1]])->assertStatus(409)->assertJsonPath('code', 'ESTADO_NO_HABILITADO');
    }

    public function test_ins_cupo_ventana_gracia_y_choque(): void
    {
        $esc = $this->escenario('CUP', ['cupo' => 1]);
        [$u, $exp] = $this->estudianteEn($esc);
        [$u2, $exp2] = $this->estudianteEn($esc, 'Gomez');
        $ciclo = $esc['ciclo']->id_ciclo_carrera;
        $g1 = $esc['grupos']['M1']->id_grupo;
        $this->withHeaders($this->headers($u) + ['Idempotency-Key' => 'a'])->postJson('/api/academico/mis-inscripciones', ['id_expediente' => $exp->id_expediente, 'id_ciclo' => $ciclo, 'grupos' => [$g1]])->assertStatus(201);
        $this->withHeaders($this->headers($u2) + ['Idempotency-Key' => 'a'])->postJson('/api/academico/mis-inscripciones', ['id_expediente' => $exp2->id_expediente, 'id_ciclo' => $ciclo, 'grupos' => [$g1]])->assertStatus(409)->assertJsonPath('code', 'CUPO_AGOTADO');
        $this->assertSame(1, Inscripcion::where('id_grupo', $g1)->where('estado', 'activa')->count());

        // Gracia: solo actores con permiso de gracia configurado. 2026-03-20 00:00 local = 06:00 UTC.
        Carbon::setTestNow('2026-03-20 06:00:00');
        $this->withHeaders($this->headers($u2))->getJson("/api/academico/mi-oferta?expediente={$exp2->id_expediente}&ciclo=$ciclo")->assertJsonPath('data.ventana.estado', 'gracia')->assertJsonPath('data.razones_generales.0.code', 'PERIODO_CERRADO');
        $esc['ciclo']->refresh();
        $conf = $esc['ciclo']->configuracion_aplicada;
        $conf['aplicacion']['permisos_gracia_inscripcion'] = ['academico_mi_inscripcion_gestionar'];
        $esc['ciclo']->update(['configuracion_aplicada' => $conf]);
        $this->withHeaders($this->headers($u2))->getJson("/api/academico/mi-oferta?expediente={$exp2->id_expediente}&ciclo=$ciclo")->assertJsonCount(0, 'data.razones_generales');
        // Fin efectivo: cerrado.
        Carbon::setTestNow('2026-03-22 06:00:00');
        $this->withHeaders($this->headers($u2))->getJson("/api/academico/mi-oferta?expediente={$exp2->id_expediente}&ciclo=$ciclo")->assertJsonPath('data.ventana.estado', 'cerrado');
    }

    public function test_doc_notas_calculo_publicacion_y_avance(): void
    {
        $esc = $this->escenario('DOC');
        [$u, $exp] = $this->estudianteEn($esc);
        $ciclo = $esc['ciclo']->id_ciclo_carrera;
        $g1 = $esc['grupos']['M1']->id_grupo;
        $this->withHeaders($this->headers($u) + ['Idempotency-Key' => 'a'])->postJson('/api/academico/mis-inscripciones', ['id_expediente' => $exp->id_expediente, 'id_ciclo' => $ciclo, 'grupos' => [$g1]])->assertStatus(201);
        $ins = Inscripcion::first();
        $hd = $this->headers($esc['cuentaDocente']);

        // Aislamiento docente.
        $otroDoc = $this->usuario(['academico_docente']);
        $this->withHeaders($this->headers($otroDoc))->getJson("/api/academico/mis-grupos/$g1/estudiantes")->assertStatus(404);
        $this->withHeaders($hd)->getJson('/api/academico/mis-grupos')->assertJsonPath('meta.total', 3);
        $this->withHeaders($hd)->getJson("/api/academico/mis-grupos/$g1/estudiantes")->assertOk()->assertJsonPath('data.0.carnet', $exp->estudiante->carnet);

        $this->withHeaders($hd)->putJson("/api/academico/mis-grupos/$g1/evaluaciones", ['evaluaciones' => [['nombre' => 'P1', 'ponderacion' => 40], ['nombre' => 'P2', 'ponderacion' => 50]]])->assertStatus(422);
        $grid = $this->withHeaders($hd)->putJson("/api/academico/mis-grupos/$g1/evaluaciones", ['evaluaciones' => [['nombre' => 'P1', 'ponderacion' => 40], ['nombre' => 'P2', 'ponderacion' => 60]]])->assertOk()->json('data');
        [$e1, $e2] = array_column($grid['evaluaciones'], 'id_evaluacion');

        // Lote con nota fuera de escala no guarda nada.
        $this->withHeaders($hd)->putJson("/api/academico/mis-grupos/$g1/notas", ['notas' => [
            ['id_evaluacion' => $e1, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => 5, 'version' => 0],
            ['id_evaluacion' => $e2, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => 11, 'version' => 0],
        ]])->assertStatus(422)->assertJsonPath('code', 'NOTAS_INVALIDAS');
        $this->assertDatabaseCount('nota_evaluacion', 0);

        $this->withHeaders($hd)->putJson("/api/academico/mis-grupos/$g1/notas", ['notas' => [['id_evaluacion' => $e1, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => 5, 'version' => 0]]])->assertOk();
        // Estructura congelada con notas.
        $this->withHeaders($hd)->putJson("/api/academico/mis-grupos/$g1/evaluaciones", ['evaluaciones' => [['nombre' => 'X', 'ponderacion' => 100]]])->assertStatus(409)->assertJsonPath('code', 'ESTRUCTURA_CON_NOTAS');
        // Versión desactualizada.
        $this->withHeaders($hd)->putJson("/api/academico/mis-grupos/$g1/notas", ['notas' => [['id_evaluacion' => $e1, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => 6, 'version' => 0]]])->assertStatus(422);

        $gestor = $this->gestorDe($esc['carrera']);
        $hg = $this->headers($gestor);
        // Nota faltante bloquea publicación.
        $calc = $this->withHeaders($hd)->postJson("/api/academico/mis-grupos/$g1/calculo-final")->assertOk()->json('data');
        $this->assertFalse($calc['completo']);
        $this->withHeaders($hg)->postJson("/api/academico/actas/$g1/publicaciones", ['version' => $calc['version_acta']])->assertStatus(409)->assertJsonPath('code', 'ACTA_INCOMPLETA');

        // Retiro con notas: 409.
        $this->withHeaders($this->headers($u))->postJson("/api/academico/mis-inscripciones/{$ins->id_inscripcion}/retiros", ['motivo' => 'x prueba'])->assertStatus(409)->assertJsonPath('code', 'INSCRIPCION_CON_NOTAS');

        $this->withHeaders($hd)->putJson("/api/academico/mis-grupos/$g1/notas", ['notas' => [['id_evaluacion' => $e2, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => 7, 'version' => 0]]])->assertOk();
        $calc = $this->withHeaders($hd)->postJson("/api/academico/mis-grupos/$g1/calculo-final")->assertOk()->json('data');
        $this->assertTrue($calc['completo']);
        $this->assertSame('6.20', $calc['resultados'][0]['nota_final']); // fixture 40/60, 5/7
        $this->assertSame('aprobada', $calc['resultados'][0]['condicion']);

        // Avance: nota borrador no cuenta.
        $this->withHeaders($this->headers($u))->getJson("/api/academico/mi-avance?expediente={$exp->id_expediente}")->assertJsonPath('data.resumen.creditos_aprobados', 0)->assertJsonPath('data.resumen.creditos_requeridos', 11);

        $this->withHeaders($this->headers($this->gestorDe($this->carrera('OTRA'))))->postJson("/api/academico/actas/$g1/publicaciones", ['version' => $calc['version_acta']])->assertStatus(404);
        $this->withHeaders($hg)->postJson("/api/academico/actas/$g1/publicaciones", ['version' => $calc['version_acta'] - 1])->assertStatus(409)->assertJsonPath('code', 'ACTA_CONFLICTO');
        $this->withHeaders($hg)->postJson("/api/academico/actas/$g1/publicaciones", ['version' => $calc['version_acta']])->assertOk()->assertJsonPath('data.estado', 'publicada');
        $this->withHeaders($hg)->postJson("/api/academico/actas/$g1/publicaciones", ['version' => $calc['version_acta']])->assertOk();
        $this->assertDatabaseCount('resultado_final', 1);
        $this->withHeaders($hd)->putJson("/api/academico/mis-grupos/$g1/notas", ['notas' => [['id_evaluacion' => $e2, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => 8, 'version' => 1]]])->assertStatus(409)->assertJsonPath('code', 'ACTA_PUBLICADA');

        // Avance y elegibilidad reflejan el resultado publicado.
        $av = $this->withHeaders($this->headers($u))->getJson("/api/academico/mi-avance?expediente={$exp->id_expediente}")->json('data');
        $this->assertEquals(4, $av['resumen']['creditos_aprobados']);
        $this->assertEquals(36.36, $av['resumen']['porcentaje']);
        $of = collect($this->withHeaders($this->headers($u))->getJson("/api/academico/mi-oferta?expediente={$exp->id_expediente}&ciclo=$ciclo")->json('data.materias'))->keyBy('codigo');
        $this->assertSame('MATERIA_YA_APROBADA', $of['DOC-M1']['razones'][0]['code']);
        $this->assertTrue($of['DOC-M2']['puede_inscribir']);

        // Fuera de plazo de notas (fin efectivo 2026-06-20 local).
        Carbon::setTestNow('2026-06-20 06:00:00');
        $this->withHeaders($this->headers($esc['cuentaDocente']))->getJson("/api/academico/mis-grupos/$g1/estudiantes")->assertOk();

        // GEN-04: conteos institucionales.
        $admin = $this->headers($this->admin());
        $this->withHeaders($admin)->getJson("/api/academico/ciclos-institucionales?id_carrera={$esc['carrera']->id_carrera}")->assertOk()
            ->assertJsonPath('data.0.inscripciones_activas', 1)->assertJsonPath('data.0.estudiantes_distintos', 1)->assertJsonPath('data.0.grupos_publicados', 3);
    }

    public function test_doc_redondeo_y_ausencias(): void
    {
        $this->assertSame('6.20', CalificacionService::redondear('6.2', 2, 'half_up'));
        $this->assertSame('6.13', CalificacionService::redondear('6.125', 2, 'half_up'));
        $this->assertSame('6.12', CalificacionService::redondear('6.125', 2, 'half_even'));
        $this->assertSame('6.12', CalificacionService::redondear('6.129', 2, 'down'));
        $this->assertSame('7', CalificacionService::redondear('6.5', 0, 'half_up'));
        $this->assertSame('6', CalificacionService::redondear('6.5', 0, 'half_even'));
    }

    public function test_gen04_conteos_excluyen_retiros_y_cuentan_estudiante_una_vez(): void
    {
        $esc = $this->escenario('G4');
        [, $exp] = $this->estudianteEn($esc);
        [, $exp2] = $this->estudianteEn($esc, 'Gomez');
        $ciclo = $esc['ciclo']->id_ciclo_carrera;
        $mk = fn ($e, $k, $estado = 'activa') => Inscripcion::create(['id_expediente' => $e->id_expediente, 'id_ciclo_carrera' => $ciclo, 'id_materia' => $esc['m'][$k]->id_materia, 'id_grupo' => $esc['grupos'][$k]->id_grupo, 'estado' => $estado, 'inscrito_en' => now()]);
        $mk($exp, 'M1');
        $mk($exp, 'M3');
        $mk($exp2, 'M1', 'retirada');
        $h = $this->headers($this->admin());
        $r = $this->withHeaders($h)->getJson("/api/academico/ciclos-institucionales/$ciclo")->assertOk();
        $r->assertJsonPath('data.inscripciones_activas', 2)->assertJsonPath('data.estudiantes_distintos', 1);
        $this->withHeaders($this->headers($this->gestorDe($esc['carrera'])))->getJson('/api/academico/ciclos-institucionales')->assertStatus(403);
    }

    public function test_exp04_autoridad_documentos_y_errores(): void
    {
        Storage::fake('local');
        Storage::fake('personalizacion');
        $esc = $this->escenario('DCM');
        [$u, $exp] = $this->estudianteEn($esc);
        $hg = $this->headers($this->gestorDe($esc['carrera'], ['academico_gestor_expediente']));
        $ha = $this->headers($this->admin());
        $id = $exp->id_expediente;

        // Sin identidad institucional.
        $this->withHeaders($hg + ['Idempotency-Key' => 'x'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'certificacion_notas'])->assertStatus(409)->assertJsonPath('code', 'IDENTIDAD_INSTITUCIONAL_INCOMPLETA');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('personalizacion')->put('plataforma/logo.png', $png);
        AppPersonalizacion::query()->updateOrCreate(['clave' => 'plataforma'], ['textos' => ['nombre' => 'Universidad de Prueba'], 'assets' => ['logo' => ['ruta' => 'plataforma/logo.png', 'mime' => 'image/png', 'hash' => hash('sha256', $png)]]]);

        // Sin autoridad.
        $this->withHeaders($hg + ['Idempotency-Key' => 'x'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'constancia_estudio'])->assertStatus(409)->assertJsonPath('code', 'AUTORIDAD_DOCUMENTAL_INVALIDA');
        $rector = $this->usuario();
        $this->withHeaders($ha)->putJson('/api/academico/documentos/autoridad', ['id_usuario' => $rector->id_usuario, 'nombre' => 'Dra. Rectora', 'cargo' => 'Rectora'])->assertOk();
        // Dos concesiones explícitas invalidan.
        $intruso = $this->usuario(['academico_autoridad_documental']);
        $this->withHeaders($hg + ['Idempotency-Key' => 'y'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'constancia_estudio'])->assertStatus(409)->assertJsonPath('code', 'AUTORIDAD_DOCUMENTAL_INVALIDA');
        // Reasignar restablece exclusividad.
        $this->withHeaders($ha)->putJson('/api/academico/documentos/autoridad', ['id_usuario' => $rector->id_usuario, 'nombre' => 'Dra. Rectora', 'cargo' => 'Rectora'])->assertOk();
        $this->assertDatabaseMissing('auth_usuario_perfil', ['id_usuario' => $intruso->id_usuario, 'codigo_perfil' => 'academico_autoridad_documental']);

        // Certificación sin resultados: no emitible.
        $this->withHeaders($hg + ['Idempotency-Key' => 'c0'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'certificacion_notas'])->assertStatus(409)->assertJsonPath('code', 'DOCUMENTO_NO_EMITIBLE');

        // Constancia: requiere inscripción activa en ciclo vigente.
        Inscripcion::create(['id_expediente' => $id, 'id_ciclo_carrera' => $esc['ciclo']->id_ciclo_carrera, 'id_materia' => $esc['m']['M1']->id_materia, 'id_grupo' => $esc['grupos']['M1']->id_grupo, 'estado' => 'activa', 'inscrito_en' => now()]);
        $d = $this->withHeaders($hg + ['Idempotency-Key' => 'k1'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'constancia_estudio'])->assertStatus(201)->json('data');
        $this->assertSame('emitido', $d['estado']);
        $this->assertSame('Rectora', $d['autoridad']['cargo']);
        $this->withHeaders($hg + ['Idempotency-Key' => 'k1'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'constancia_estudio'])->assertOk()->assertJsonPath('data.folio', $d['folio']);
        $pdf = $this->withHeaders($hg)->get("/api/academico/expedientes/$id/documentos/{$d['id_documento']}/archivo")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());
        $ajeno = $this->headers($this->gestorDe($this->carrera('Q'), ['academico_gestor_expediente']));
        $this->withHeaders($ajeno)->get("/api/academico/expedientes/$id/documentos/{$d['id_documento']}/archivo")->assertStatus(404);

        // Certificación con muchos resultados → varias páginas.
        $acta = ActaGrupo::create(['id_grupo' => $esc['grupos']['M1']->id_grupo, 'id_escala' => $esc['escala']->id_escala, 'estado' => 'publicada', 'publicado_en' => now()]);
        $ins = Inscripcion::first();
        ResultadoFinal::create(['id_inscripcion' => $ins->id_inscripcion, 'id_acta' => $acta->id_acta, 'nota_final' => 8.5, 'condicion' => 'aprobada', 'creditos' => 4, 'id_escala' => $esc['escala']->id_escala, 'version_politica' => 1]);
        $c = $this->withHeaders($hg + ['Idempotency-Key' => 'k2'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'certificacion_notas'])->assertStatus(201)->json('data');
        $this->assertSame(1, $c['paginas']);
        $doc = DocumentoExpediente::find($c['id_documento']);
        $inst = $doc->instantanea;
        $inst['resultados'] = array_fill(0, 80, $inst['resultados'][0]);
        $doc->instantanea = $inst;
        [, $paginas] = app(DocumentoService::class)->renderizar($doc);
        $this->assertGreaterThan(1, $paginas);

        // Cambio de autoridad afecta nuevas emisiones y preserva las previas.
        $nuevo = $this->usuario();
        $this->withHeaders($ha)->putJson('/api/academico/documentos/autoridad', ['id_usuario' => $nuevo->id_usuario, 'nombre' => 'Dr. Nuevo', 'cargo' => 'Rector interino'])->assertOk();
        $this->assertSame('Rectora', DocumentoExpediente::find($d['id_documento'])->cargo_autoridad);
        $n = $this->withHeaders($hg + ['Idempotency-Key' => 'k3'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'constancia_estudio'])->json('data');
        $this->assertSame('Rector interino', $n['autoridad']['cargo']);
        // Titular inactivo bloquea.
        $nuevo->update(['id_estado' => 'INACTIVO']);
        $this->withHeaders($hg + ['Idempotency-Key' => 'k4'])->postJson("/api/academico/expedientes/$id/documentos", ['tipo' => 'constancia_estudio'])->assertStatus(409)->assertJsonPath('code', 'AUTORIDAD_DOCUMENTAL_INVALIDA');
    }
}
