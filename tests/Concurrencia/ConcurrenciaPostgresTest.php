<?php

namespace Tests\Concurrencia;

use App\Models\Academico\ActaGrupo;
use App\Models\Academico\EvaluacionGrupo;
use App\Models\Academico\Expediente;
use App\Models\Academico\IaEjecucion;
use App\Models\Academico\IaPresupuesto;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\NotaEvaluacion;
use App\Models\Academico\OrdenPagoIngreso;
use App\Models\Academico\ResultadoFinal;
use App\Models\Academico\SolicitudIngreso;
use App\Models\Academico\SolicitudIngresoRevision;
use App\Models\Auth\AuthUsuario;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;
use Tests\Feature\Academico\AcademicoHelpers;

/**
 * Pruebas concurrentes reales sobre la base PostgreSQL exclusiva de pruebas `ues_academico_test`
 * (ENTREGA.md, INS-02, EXP-02, DOC-02, IA-BASE-01).
 *
 * Cada competidor es un proceso PHP independiente (artisan academico:concurrencia) con su propia
 * conexión; todos esperan el mismo instante antes de ejecutar. Los datos se confirman (no hay
 * transacción envolvente) y la base se recrea con migrate:fresh en cada prueba.
 *
 * Ejecutar: php artisan test --testsuite=Concurrencia
 */
class ConcurrenciaPostgresTest extends BaseTestCase
{
    use AcademicoHelpers, CreatesApplication;

    private const BASE = 'ues_academico_test';

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== self::BASE) {
            $this->markTestSkipped('Requiere DB_CONNECTION=pgsql y DB_DATABASE='.self::BASE.' (suite Concurrencia).');
        }
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
        // Los procesos hijos usan el reloj real: el escenario se fija en "ahora".
        Carbon::setTestNow();
    }

    /**
     * Lanza N procesos simultáneos y devuelve sus resultados JSON.
     *
     * @param  array<int, array{0:string,1:array}>  $tareas
     * @return array<int, array{ok:bool, code:?string, data:mixed}>
     */
    private function competir(array $tareas): array
    {
        $en = microtime(true) + 2.0;
        $env = array_merge(getenv(), ['DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => self::BASE, 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array']);
        $procs = [];
        foreach ($tareas as $i => [$op, $args]) {
            $cmd = [PHP_BINARY, base_path('artisan'), 'academico:concurrencia', $op, '--en='.$en, '--json='.json_encode($args)];
            $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i], base_path(), $env);
        }
        $out = [];
        foreach ($procs as $i => $p) {
            $salida = stream_get_contents($pipes[$i][1]);
            $err = stream_get_contents($pipes[$i][2]);
            proc_close($p);
            $linea = collect(explode("\n", trim($salida)))->last();
            $out[$i] = json_decode((string) $linea, true) ?? ['ok' => false, 'code' => 'SIN_SALIDA:'.mb_substr($salida.$err, 0, 300), 'data' => null];
        }

        return $out;
    }

    private function escenarioAhora(array $opciones = []): array
    {
        $hoy = now('America/El_Salvador');
        $opciones['ahora'] = now();
        $opciones['calendario'] = [
            'inicio' => $hoy->copy()->subMonth()->format('Y-m-d H:i'), 'fin' => $hoy->copy()->addMonths(4)->format('Y-m-d H:i'),
            'ventanas' => [
                'inscripcion' => ['inicio' => $hoy->copy()->subDays(5)->format('Y-m-d H:i'), 'fin_regular' => $hoy->copy()->addDays(10)->format('Y-m-d H:i'), 'fin_gracia' => null],
                'notas' => ['inicio' => $hoy->copy()->subDays(5)->format('Y-m-d H:i'), 'fin_regular' => $hoy->copy()->addMonths(2)->format('Y-m-d H:i'), 'fin_gracia' => null],
            ],
        ];

        return $this->escenario('CON', $opciones);
    }

    public function test_ultimo_cupo_con_ocho_estudiantes_simultaneos_deja_un_solo_inscrito(): void
    {
        $esc = $this->escenarioAhora(['cupo' => 1]);
        $g1 = $esc['grupos']['M1']->id_grupo;
        $tareas = [];
        for ($i = 0; $i < 8; $i++) {
            [$u, $exp] = $this->estudianteEn($esc, 'Comp'.$i);
            $tareas[] = ['inscribir', ['id_usuario' => $u->id_usuario, 'id_expediente' => $exp->id_expediente, 'id_ciclo' => $esc['ciclo']->id_ciclo_carrera, 'grupos' => [$g1]]];
        }
        $r = $this->competir($tareas);

        $exitos = collect($r)->where('ok', true)->count();
        $this->assertSame(1, $exitos, json_encode($r));
        $this->assertSame(7, collect($r)->where('code', 'CUPO_AGOTADO')->count(), json_encode($r));
        $this->assertSame(1, Inscripcion::where('id_grupo', $g1)->where('estado', 'activa')->count());
    }

    public function test_mismo_estudiante_dos_confirmaciones_simultaneas_no_duplica_materia_ni_cupo(): void
    {
        $esc = $this->escenarioAhora(['cupo' => 5]);
        [$u, $exp] = $this->estudianteEn($esc);
        $args = ['id_usuario' => $u->id_usuario, 'id_expediente' => $exp->id_expediente, 'id_ciclo' => $esc['ciclo']->id_ciclo_carrera, 'grupos' => [$esc['grupos']['M1']->id_grupo, $esc['grupos']['M3']->id_grupo]];
        $r = $this->competir([['inscribir', $args], ['inscribir', $args], ['inscribir', $args]]);

        $this->assertGreaterThanOrEqual(1, collect($r)->where('ok', true)->count(), json_encode($r));
        $this->assertSame(2, Inscripcion::where('id_expediente', $exp->id_expediente)->where('estado', 'activa')->count());
        $this->assertSame(1, Inscripcion::where('id_grupo', $esc['grupos']['M1']->id_grupo)->count());
    }

    public function test_dos_aceptaciones_simultaneas_crean_un_expediente_y_un_carnet(): void
    {
        $esc = $this->escenarioAhora();
        $sede = \App\Models\Academico\Sede::create(['codigo' => 'SS', 'nombre' => 'San Salvador']);
        $fac = \App\Models\Academico\Facultad::create(['codigo' => 'FIA', 'nombre' => 'Ingeniería']);
        $conv = \App\Models\Academico\ConvocatoriaIngreso::create(['id_carrera' => $esc['carrera']->id_carrera, 'codigo' => 'A1', 'titulo_publico' => 'A1', 'estado' => 'publicada', 'requiere_pago' => true, 'monto_pago' => 10, 'inicio' => now()->subDay(), 'fin' => now()->addMonth()]);
        $aspirante = $this->usuario(['academico_aspirante']);
        $sol = SolicitudIngreso::create([
            'id_convocatoria' => $conv->id_convocatoria, 'id_usuario' => $aspirante->id_usuario, 'folio' => 'SOL-CONC', 'version_requisitos' => 1,
            'nombres' => 'María', 'primer_apellido' => 'Sibrián', 'segundo_apellido' => 'Hernández', 'email' => $aspirante->email, 'fecha_nacimiento' => '2000-01-01',
            'nacionalidad' => 'salvadoreña', 'id_carrera' => $esc['carrera']->id_carrera, 'id_sede' => $sede->id_sede, 'id_facultad' => $fac->id_facultad,
            'modalidad_estudio' => 'presencial', 'modalidad_ingreso' => 'general',
        ]);
        SolicitudIngresoRevision::create(['id_solicitud' => $sol->id_solicitud, 'revision' => 1, 'datos' => ['formulario' => []], 'edad' => 26, 'instante' => now()]);
        OrdenPagoIngreso::create(['id_usuario' => $aspirante->id_usuario, 'id_convocatoria' => $conv->id_convocatoria, 'folio' => 'OP-C', 'monto' => 10, 'estado' => 'validado']);
        $gestor = $this->gestorDe($esc['carrera'], ['academico_gestor_expediente']);
        $args = ['id_solicitud' => $sol->id_solicitud, 'version' => 1, 'fecha_ingreso' => now()->toDateString(), 'actor' => $gestor->id_usuario];
        $r = $this->competir([['aceptar', $args], ['aceptar', $args], ['aceptar', $args], ['aceptar', $args]]);

        $this->assertSame(4, collect($r)->where('ok', true)->count(), json_encode($r)); // reintento equivalente devuelve el mismo expediente
        $this->assertCount(1, collect($r)->pluck('data')->unique());
        $this->assertSame(1, Expediente::where('id_solicitud', $sol->id_solicitud)->count());
        $this->assertSame(1, \App\Models\Academico\Estudiante::where('id_usuario', $aspirante->id_usuario)->count());
        $this->assertSame(1, (int) DB::table('carnet_correlativo')->where('prefijo', 'SH')->value('ultimo'));
    }

    public function test_dos_admisiones_simultaneas_con_mismas_iniciales_reciben_correlativos_distintos(): void
    {
        $esc = $this->escenarioAhora();
        $sede = \App\Models\Academico\Sede::create(['codigo' => 'SS', 'nombre' => 'San Salvador']);
        $fac = \App\Models\Academico\Facultad::create(['codigo' => 'FIA', 'nombre' => 'Ingeniería']);
        $conv = \App\Models\Academico\ConvocatoriaIngreso::create(['id_carrera' => $esc['carrera']->id_carrera, 'codigo' => 'A2', 'titulo_publico' => 'A2', 'estado' => 'publicada', 'requiere_pago' => false, 'inicio' => now()->subDay(), 'fin' => now()->addMonth()]);
        $gestor = $this->gestorDe($esc['carrera'], ['academico_gestor_expediente']);
        $tareas = [];
        foreach (['Sosa', 'Sibrián', 'Salazar', 'Santos'] as $i => $ap) {
            $a = $this->usuario(['academico_aspirante']);
            $s = SolicitudIngreso::create(['id_convocatoria' => $conv->id_convocatoria, 'id_usuario' => $a->id_usuario, 'folio' => 'SOL-'.$i, 'version_requisitos' => 1,
                'nombres' => 'N', 'primer_apellido' => $ap, 'segundo_apellido' => 'Hernández', 'email' => $a->email, 'fecha_nacimiento' => '2000-01-01', 'nacionalidad' => 'salvadoreña',
                'id_carrera' => $esc['carrera']->id_carrera, 'id_sede' => $sede->id_sede, 'id_facultad' => $fac->id_facultad, 'modalidad_estudio' => 'presencial', 'modalidad_ingreso' => 'general']);
            SolicitudIngresoRevision::create(['id_solicitud' => $s->id_solicitud, 'revision' => 1, 'datos' => ['formulario' => []], 'edad' => 26, 'instante' => now()]);
            $tareas[] = ['aceptar', ['id_solicitud' => $s->id_solicitud, 'version' => 1, 'fecha_ingreso' => now()->toDateString(), 'actor' => $gestor->id_usuario]];
        }
        $r = $this->competir($tareas);

        $this->assertSame(4, collect($r)->where('ok', true)->count(), json_encode($r));
        $carnets = \App\Models\Academico\Estudiante::where('carnet_prefijo', 'SH')->pluck('carnet')->sort()->values()->all();
        $anio = now('America/El_Salvador')->year;
        $this->assertSame(["SH{$anio}01", "SH{$anio}02", "SH{$anio}03", "SH{$anio}04"], $carnets);
    }

    public function test_dos_publicaciones_simultaneas_de_acta_producen_un_resultado_por_inscripcion(): void
    {
        $esc = $this->escenarioAhora();
        $g = $esc['grupos']['M1'];
        $insc = [];
        for ($i = 0; $i < 3; $i++) {
            [, $exp] = $this->estudianteEn($esc, 'Pub'.$i);
            $insc[] = Inscripcion::create(['id_expediente' => $exp->id_expediente, 'id_ciclo_carrera' => $esc['ciclo']->id_ciclo_carrera, 'id_materia' => $esc['m']['M1']->id_materia, 'id_grupo' => $g->id_grupo, 'estado' => 'activa', 'inscrito_en' => now()]);
        }
        $acta = ActaGrupo::create(['id_grupo' => $g->id_grupo, 'id_escala' => $esc['escala']->id_escala, 'estado' => 'borrador', 'version' => 5]);
        $e1 = EvaluacionGrupo::create(['id_grupo' => $g->id_grupo, 'id_escala' => $esc['escala']->id_escala, 'nombre' => 'P1', 'ponderacion' => 40, 'orden' => 1]);
        $e2 = EvaluacionGrupo::create(['id_grupo' => $g->id_grupo, 'id_escala' => $esc['escala']->id_escala, 'nombre' => 'P2', 'ponderacion' => 60, 'orden' => 2]);
        foreach ($insc as $ins) {
            NotaEvaluacion::create(['id_evaluacion' => $e1->id_evaluacion, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => 5, 'id_usuario' => $esc['cuentaDocente']->id_usuario]);
            NotaEvaluacion::create(['id_evaluacion' => $e2->id_evaluacion, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => 7, 'id_usuario' => $esc['cuentaDocente']->id_usuario]);
        }
        $gestor = $this->gestorDe($esc['carrera']);
        $args = ['id_grupo' => $g->id_grupo, 'version' => 5, 'actor' => $gestor->id_usuario];
        $r = $this->competir([['publicar', $args], ['publicar', $args], ['publicar', $args]]);

        $this->assertSame(3, collect($r)->where('ok', true)->count(), json_encode($r)); // reintento sobre la misma versión devuelve el acta publicada
        $this->assertSame('publicada', $acta->fresh()->estado);
        $this->assertSame(3, ResultadoFinal::where('id_acta', $acta->id_acta)->count());
        $this->assertSame('6.2000', (string) ResultadoFinal::first()->nota_final);
    }

    public function test_reservas_de_ia_concurrentes_que_exceden_el_saldo_solo_admiten_las_que_caben(): void
    {
        app(\App\Services\IA\PresupuestoIAService::class)->fila('simulacion');
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = IaEjecucion::create(['propietario' => 'usuario:x'.$i, 'agente' => 'estudiantes', 'clave_turno' => 'k'.$i, 'hash_cuerpo' => 'h', 'proveedor' => 'mock', 'modelo' => 'mock-academico-1', 'simulado' => true])->id_ejecucion;
        }
        // Límite 1.00 USD y reservas de 0.30: caben 3 de 6.
        $r = $this->competir(array_map(fn ($id) => ['reservar', ['id_ejecucion' => $id, 'monto' => '0.300000000000', 'limite' => '1.00']], $ids));

        $this->assertSame(3, collect($r)->where('ok', true)->count(), json_encode($r));
        $this->assertSame(3, collect($r)->where('code', 'IA_PRESUPUESTO_INSUFICIENTE')->count(), json_encode($r));
        $this->assertEqualsWithDelta(0.9, (float) IaPresupuesto::where('ambito', 'simulacion')->value('reservado'), 1e-9);
        $this->assertSame(3, \App\Models\Academico\IaMovimientoGasto::where('tipo', 'reserva')->count());
    }

    protected function tearDown(): void
    {
        // Deja la base de pruebas limpia de procesos/conexiones abiertas.
        DB::disconnect();
        parent::tearDown();
    }
}
