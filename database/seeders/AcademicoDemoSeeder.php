<?php

namespace Database\Seeders;

use App\Models\Academico\ActaGrupo;
use App\Models\Academico\Carrera;
use App\Models\Academico\Docente;
use App\Models\Academico\Estudiante;
use App\Models\Academico\EvaluacionGrupo;
use App\Models\Academico\Expediente;
use App\Models\Academico\ExpedienteEvento;
use App\Models\Academico\Facultad;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\InscripcionEvento;
use App\Models\Academico\Materia;
use App\Models\Academico\NotaEvaluacion;
use App\Models\Academico\ResultadoFinal;
use App\Models\Academico\Sede;
use App\Models\App\AppPersonalizacion;
use App\Models\Auth\AuthUsuario;
use App\Services\Academico\CalendarioService;
use App\Services\Academico\CalificacionService;
use App\Services\Academico\CatalogoService;
use App\Services\Academico\ConfiguracionService;
use App\Services\Academico\ConvocatoriaService;
use App\Services\Academico\DocumentoService;
use App\Services\Academico\ExpedienteEstados;
use App\Services\Academico\IdentidadAccesoService;
use App\Services\Academico\OfertaService;
use App\Services\Academico\PensumService;
use App\Services\IA\FuentesIAService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de demostración con credenciales de prueba (solo entorno local).
 *
 * Contraseña común: Demo2026!  — ver docs/CURRENT-STATE.md para la tabla de cuentas.
 * Idempotente: si la carrera demo ya existe, no vuelve a crear el escenario.
 */
class AcademicoDemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo2026!';

    private string $admin = DatabaseSeeder::ID_USUARIO_ADMINISTRADOR;

    public function run(): void
    {
        if (Carrera::query()->where('codigo', 'ISI')->exists()) {
            return;
        }
        DB::transaction(function () {
            $this->marca();
            $conf = app(ConfiguracionService::class);
            $escala = $conf->crearEscala(['codigo' => 'UES', 'nombre' => 'Escala 0–10 (demostración)', 'minimo' => 0, 'maximo' => 10, 'umbral_aprobacion' => 6, 'precision' => 2, 'redondeo' => 'half_up', 'ponderacion_total' => 100, 'ausencia' => 'minimo'], $this->admin);
            $hoy = CarbonImmutable::now($conf->zona());
            $conf->actualizar([
                'calificaciones' => ['id_escala_vigente' => $escala->id_escala],
                'calendario' => [
                    'ciclo_inicio' => $hoy->subMonth()->format('Y-m-d'), 'ciclo_fin' => $hoy->addMonths(4)->format('Y-m-d'),
                    'inscripcion_inicio' => $hoy->subDays(10)->format('Y-m-d 00:00'), 'inscripcion_fin' => $hoy->addDays(20)->format('Y-m-d 00:00'),
                    'notas_inicio' => $hoy->subDays(5)->format('Y-m-d 00:00'), 'notas_fin' => $hoy->addMonths(3)->format('Y-m-d 00:00'),
                    'gracia_horas_inscripcion' => 48, 'gracia_horas_notas' => 72,
                ],
                'credenciales' => ['contacto_email' => 'orientacion@ues.test', 'contacto_telefono' => '2511-2000', 'contacto_url' => 'https://www.ues.edu.sv'],
            ], $conf->revisionActual(), 'Configuración de demostración', $this->admin);

            // Cuentas de prueba.
            $u = fn (string $user, string $email, array $perfiles) => $this->cuenta($user, $email, $perfiles);
            $academico = $u('academico.admin', 'admin.academico@ues.test', ['academico_admin']);
            $gestorIsi = $u('gestor.isi', 'gestor.isi@ues.test', ['academico_gestor_carrera', 'academico_gestor_expediente']);
            $gestorIca = $u('gestor.ica', 'gestor.ica@ues.test', ['academico_gestor_carrera', 'academico_gestor_expediente']);
            $doc1 = $u('docente.lopez', 'docente.lopez@ues.test', ['academico_docente']);
            $doc2 = $u('docente.martinez', 'docente.martinez@ues.test', ['academico_docente']);
            $rector = $u('rectoria', 'rectoria@ues.test', []);
            $aspirante = $u('aspirante.demo@ues.test', 'aspirante.demo@ues.test', ['academico_aspirante']);

            // Catálogos.
            $cat = app(CatalogoService::class);
            $sedes = collect([['SS', 'San Salvador'], ['SA', 'Santa Ana'], ['SV', 'San Vicente'], ['SM', 'San Miguel']])->map(fn ($s) => Sede::create(['codigo' => $s[0], 'nombre' => 'Sede '.$s[1]]));
            $fia = Facultad::create(['codigo' => 'FIA', 'nombre' => 'Facultad de Ingeniería y Arquitectura']);
            $isi = Carrera::create(['codigo' => 'ISI', 'nombre' => 'Ingeniería de Sistemas Informáticos', 'descripcion_publica' => 'Forma profesionales en desarrollo de software, bases de datos, redes y gestión de proyectos tecnológicos.', 'id_facultad' => $fia->id_facultad]);
            $ica = Carrera::create(['codigo' => 'ICA', 'nombre' => 'Ingeniería Civil', 'descripcion_publica' => 'Forma profesionales en diseño y construcción de obras civiles.', 'id_facultad' => $fia->id_facultad]);
            $isi->sedes()->sync($sedes->pluck('id_sede'));
            $ica->sedes()->sync([$sedes[0]->id_sede]);
            $cat->asignarGestor($isi, $gestorIsi->id_usuario, $this->admin);
            $cat->asignarGestor($ica, $gestorIca->id_usuario, $this->admin);

            $mat = [];
            foreach ([
                ['MAT115', 'Matemática I', 1, 4], ['PRN115', 'Programación I', 1, 4], ['IAI115', 'Introducción a la Informática', 1, 3],
                ['MAT215', 'Matemática II', 2, 4], ['PRN215', 'Programación II', 2, 4], ['ESD115', 'Estructuras de Datos', 2, 4],
                ['BAD115', 'Bases de Datos', 3, 4], ['SIO115', 'Sistemas Operativos', 3, 4],
            ] as [$c, $n, $nivel, $cr]) {
                $mat[$c] = ['m' => Materia::create(['codigo' => $c, 'nombre' => $n]), 'nivel' => $nivel, 'cr' => $cr];
            }
            $pensumSvc = app(PensumService::class);
            $p = $pensumSvc->crear($isi, '2026', null, $this->admin);
            $p = $pensumSvc->actualizarBorrador($p, [
                'version' => 1, 'vigente_desde' => $hoy->startOfYear()->format('Y-m-d'),
                'materias' => collect($mat)->map(fn ($x) => ['id_materia' => $x['m']->id_materia, 'nivel' => $x['nivel'], 'creditos' => $x['cr']])->values()->all(),
                'requisitos' => [
                    ['id_materia' => $mat['MAT215']['m']->id_materia, 'id_materia_requisito' => $mat['MAT115']['m']->id_materia],
                    ['id_materia' => $mat['PRN215']['m']->id_materia, 'id_materia_requisito' => $mat['PRN115']['m']->id_materia],
                    ['id_materia' => $mat['ESD115']['m']->id_materia, 'id_materia_requisito' => $mat['PRN115']['m']->id_materia],
                    ['id_materia' => $mat['BAD115']['m']->id_materia, 'id_materia_requisito' => $mat['ESD115']['m']->id_materia],
                    ['id_materia' => $mat['SIO115']['m']->id_materia, 'id_materia_requisito' => $mat['IAI115']['m']->id_materia],
                ],
            ], $this->admin);
            $pensum = $pensumSvc->transicion($p, 'publicado', (int) $p->version, $this->admin);
            $mci = Materia::create(['codigo' => 'MCI115', 'nombre' => 'Mecánica de Suelos']);
            $pc = $pensumSvc->crear($ica, '2026', null, $this->admin);
            $pc = $pensumSvc->actualizarBorrador($pc, ['version' => 1, 'vigente_desde' => $hoy->startOfYear()->format('Y-m-d'), 'materias' => [['id_materia' => $mci->id_materia, 'nivel' => 1, 'creditos' => 4], ['id_materia' => $mat['MAT115']['m']->id_materia, 'nivel' => 1, 'creditos' => 4]]], $this->admin);
            $pensumSvc->transicion($pc, 'publicado', (int) $pc->version, $this->admin);

            [$d1] = $cat->crearDocente(['id_usuario' => $doc1->id_usuario, 'codigo' => 'DOC-001', 'nombre_academico' => 'Ing. Ana López'], $this->admin);
            [$d2] = $cat->crearDocente(['id_usuario' => $doc2->id_usuario, 'codigo' => 'DOC-002', 'nombre_academico' => 'Ing. Carlos Martínez'], $this->admin);

            // Ciclo pasado (cerrado con resultados) y ciclo vigente.
            $cal = app(CalendarioService::class);
            $of = app(OfertaService::class);
            $ciclo = $cal->crear($isi, ['codigo' => 'CI-'.$hoy->year, 'nombre' => 'Ciclo I '.$hoy->year], $this->admin);
            $ciclo = $cal->transicion($ciclo, 'publicado', (int) $ciclo->version, $this->admin);
            $horarios = ['MAT115' => [1, '07:00', '09:00'], 'PRN115' => [2, '07:00', '09:00'], 'IAI115' => [3, '07:00', '09:00'], 'MAT215' => [1, '09:00', '11:00'], 'PRN215' => [2, '09:00', '11:00'], 'ESD115' => [4, '07:00', '09:00'], 'BAD115' => [5, '07:00', '09:00'], 'SIO115' => [3, '09:00', '11:00']];
            $grupos = [];
            foreach ($horarios as $codigo => [$dia, $ini, $fin]) {
                $oferta = $of->crearOferta($ciclo, $mat[$codigo]['m']->id_materia, $this->admin);
                $g = $of->crearGrupo($oferta, ['codigo' => 'GT01', 'cupo' => $codigo === 'PRN115' ? 2 : 30, 'horarios' => [['dia' => $dia, 'hora_inicio' => $ini, 'hora_fin' => $fin]], 'id_docente' => in_array($codigo, ['MAT115', 'MAT215', 'BAD115'], true) ? $d2->id_docente : $d1->id_docente], $this->admin);
                $grupos[$codigo] = $of->actualizarGrupo($g, ['version' => 1, 'estado' => 'publicado'], $this->admin);
            }

            // Estudiantes con carnet D01 (cuentas temporales ya cambiadas para pruebas).
            $identidad = app(IdentidadAccesoService::class);
            $est1 = $this->estudiante($identidad, 'María Rosa', 'Sibrián', 'Hernández', 'maria.sibrian@ues.test', $isi->id_carrera, $pensum->id_pensum, $hoy->startOfYear()->format('Y-m-d'));
            $est2 = $this->estudiante($identidad, 'José', 'Pérez', null, 'jose.perez@ues.test', $isi->id_carrera, $pensum->id_pensum, $hoy->startOfYear()->format('Y-m-d'));

            // Historial publicado de María en un ciclo anterior (MAT115, PRN115, IAI115 aprobadas).
            $anterior = $cal->crear($isi, ['codigo' => 'CII-'.($hoy->year - 1), 'nombre' => 'Ciclo II '.($hoy->year - 1), 'calendario' => [
                'inicio' => $hoy->subMonths(8)->format('Y-m-d 00:00'), 'fin' => $hoy->subMonths(2)->format('Y-m-d 00:00'),
                'ventanas' => ['inscripcion' => ['inicio' => $hoy->subMonths(8)->format('Y-m-d 00:00'), 'fin_regular' => $hoy->subMonths(7)->format('Y-m-d 00:00'), 'fin_gracia' => null],
                    'notas' => ['inicio' => $hoy->subMonths(8)->format('Y-m-d 00:00'), 'fin_regular' => $hoy->subMonths(3)->format('Y-m-d 00:00'), 'fin_gracia' => null]],
            ]], $this->admin);
            $anterior = $cal->transicion($anterior, 'publicado', (int) $anterior->version, $this->admin);
            foreach (['MAT115' => [7, 8], 'PRN115' => [9, 8.5], 'IAI115' => [6, 7]] as $codigo => [$n1, $n2]) {
                $oferta = $of->crearOferta($anterior, $mat[$codigo]['m']->id_materia, $this->admin);
                $g = $of->crearGrupo($oferta, ['codigo' => 'GT01', 'cupo' => 30, 'horarios' => [['dia' => 6, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']], 'id_docente' => $d1->id_docente], $this->admin);
                $g = $of->actualizarGrupo($g, ['version' => 1, 'estado' => 'publicado'], $this->admin);
                $ins = Inscripcion::create(['id_expediente' => $est1['exp']->id_expediente, 'id_ciclo_carrera' => $anterior->id_ciclo_carrera, 'id_materia' => $mat[$codigo]['m']->id_materia, 'id_grupo' => $g->id_grupo, 'estado' => 'activa', 'inscrito_en' => $hoy->subMonths(7)]);
                $acta = ActaGrupo::create(['id_grupo' => $g->id_grupo, 'id_escala' => $escala->id_escala, 'estado' => 'borrador']);
                $e1 = EvaluacionGrupo::create(['id_grupo' => $g->id_grupo, 'id_escala' => $escala->id_escala, 'nombre' => 'Parcial', 'ponderacion' => 40, 'orden' => 1]);
                $e2 = EvaluacionGrupo::create(['id_grupo' => $g->id_grupo, 'id_escala' => $escala->id_escala, 'nombre' => 'Final', 'ponderacion' => 60, 'orden' => 2]);
                NotaEvaluacion::create(['id_evaluacion' => $e1->id_evaluacion, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => $n1, 'id_usuario' => $doc1->id_usuario]);
                NotaEvaluacion::create(['id_evaluacion' => $e2->id_evaluacion, 'id_inscripcion' => $ins->id_inscripcion, 'valor' => $n2, 'id_usuario' => $doc1->id_usuario]);
                $calc = app(CalificacionService::class)->calcular($g, $acta);
                foreach ($calc['resultados'] as $r) {
                    ResultadoFinal::create(['id_inscripcion' => $r['id_inscripcion'], 'id_acta' => $acta->id_acta, 'nota_final' => $r['nota_final'], 'condicion' => $r['condicion'], 'creditos' => $r['condicion'] === 'aprobada' ? $r['creditos'] : 0, 'id_escala' => $escala->id_escala, 'version_politica' => $escala->version_politica, 'detalle' => $r['contribuciones']]);
                }
                $acta->update(['estado' => 'publicada', 'publicado_por' => $gestorIsi->id_usuario, 'publicado_en' => $hoy->subMonths(3)]);
            }
            $cal->transicion($anterior->fresh(), 'cerrado', (int) $anterior->fresh()->version, $this->admin);

            // María inscrita en MAT215 del ciclo vigente (para constancia y notas del docente).
            $insM = Inscripcion::create(['id_expediente' => $est1['exp']->id_expediente, 'id_ciclo_carrera' => $ciclo->id_ciclo_carrera, 'id_materia' => $mat['MAT215']['m']->id_materia, 'id_grupo' => $grupos['MAT215']->id_grupo, 'estado' => 'activa', 'inscrito_en' => now()]);
            InscripcionEvento::create(['id_inscripcion' => $insM->id_inscripcion, 'operacion' => 'alta', 'id_grupo_nuevo' => $grupos['MAT215']->id_grupo, 'id_usuario' => $est1['cuenta']->id_usuario, 'instante' => now()]);

            // Convocatoria abierta de admisión.
            $conv = app(ConvocatoriaService::class)->crear($isi, [
                'codigo' => 'ADM-'.($hoy->year + 1), 'titulo_publico' => 'Admisión '.($hoy->year + 1).' — Ingeniería de Sistemas Informáticos',
                'descripcion_publica' => 'Proceso de ingreso general y modalidades especiales.', 'inicio' => $hoy->subDays(5)->format('Y-m-d 00:00'), 'fin' => $hoy->addMonth()->format('Y-m-d 00:00'),
                'ofertas' => $sedes->flatMap(fn ($s) => [
                    ['id_sede' => $s->id_sede, 'id_facultad' => $fia->id_facultad, 'modalidad_estudio' => 'presencial', 'modalidad_ingreso' => 'general'],
                    ['id_sede' => $s->id_sede, 'id_facultad' => $fia->id_facultad, 'modalidad_estudio' => 'presencial', 'modalidad_ingreso' => 'especial'],
                ])->all(),
            ], $this->admin);
            app(ConvocatoriaService::class)->actualizar($conv, ['version' => 1, 'estado' => 'publicada'], $gestorIsi->id_usuario);

            // Autoridad documental y fuentes IA publicadas.
            app(DocumentoService::class)->asignarAutoridad($rector->id_usuario, 'Dra. Rectora de Demostración', 'Rectora', $this->admin);
            $fuentes = app(FuentesIAService::class);
            foreach ([$isi, $ica] as $c) {
                $fuentes->publicar($c, collect($fuentes->proyectar($c))->map(fn ($p) => ['tipo' => $p['tipo'], 'id_origen' => $p['id_origen']])->all(), 'publicada', $this->admin);
            }
        });
    }

    private function cuenta(string $username, string $email, array $perfiles): AuthUsuario
    {
        $u = AuthUsuario::query()->firstOrCreate(['email' => $email], [
            'username' => strtolower($username), 'password' => self::PASSWORD, 'fecha_verificacion' => now(), 'id_estado' => 'ACTIVO', 'creado_por' => $this->admin,
        ]);
        foreach (array_unique([strtolower($email), strtolower($username)]) as $clave) {
            DB::table('auth_identificador')->insertOrIgnore(['clave' => $clave, 'id_usuario' => $u->id_usuario, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($perfiles as $p) {
            app(CatalogoService::class)->asegurarPerfil($u->id_usuario, $p, $this->admin);
        }

        return $u;
    }

    /**
     * @return array{cuenta: AuthUsuario, est: Estudiante, exp: Expediente}
     */
    private function estudiante(IdentidadAccesoService $identidad, string $nombres, string $ap1, ?string $ap2, string $email, int $idCarrera, int $idPensum, string $ingreso): array
    {
        $carnet = $identidad->reservarCarnet($ap1, $ap2);
        $cuenta = $this->cuenta(strtolower($carnet['carnet']), $email, ['academico_estudiante']);
        $est = Estudiante::create([
            'id_usuario' => $cuenta->id_usuario, 'carnet' => $carnet['carnet'], 'carnet_prefijo' => $carnet['prefijo'], 'carnet_anio' => $carnet['anio'],
            'carnet_correlativo' => $carnet['correlativo'], 'carnet_regla' => $carnet['regla'], 'nombres' => $nombres, 'primer_apellido' => $ap1, 'segundo_apellido' => $ap2, 'email' => $email,
        ]);
        $exp = Expediente::create(['id_estudiante' => $est->id_estudiante, 'id_carrera' => $idCarrera, 'id_pensum' => $idPensum, 'fecha_ingreso' => $ingreso, 'estado' => ExpedienteEstados::ACTIVO]);
        ExpedienteEvento::create(['id_expediente' => $exp->id_expediente, 'tipo' => 'admision', 'estado_nuevo' => 'ACTIVO', 'motivo' => 'Datos de demostración', 'id_usuario' => $this->admin, 'instante' => now()]);

        return ['cuenta' => $cuenta, 'est' => $est, 'exp' => $exp];
    }

    /**
     * Marca blanca de demostración (nombre + logo SVG→PNG generado).
     */
    private function marca(): void
    {
        $fila = AppPersonalizacion::query()->find('plataforma');
        if ($fila && ! empty($fila->textos['nombre'])) {
            return;
        }
        $png = $this->logoPng();
        $ruta = 'plataforma/logo-demo.png';
        Storage::disk('personalizacion')->put($ruta, $png);
        $assets = ['logo' => ['ruta' => $ruta, 'mime' => 'image/png', 'hash' => hash('sha256', $png)], 'logo_vertical' => ['ruta' => $ruta, 'mime' => 'image/png', 'hash' => hash('sha256', $png)], 'favicon' => ['ruta' => $ruta, 'mime' => 'image/png', 'hash' => hash('sha256', $png)]];
        AppPersonalizacion::query()->updateOrCreate(['clave' => 'plataforma'], [
            'textos' => ['nombre' => 'Universidad de El Salvador (Demo)', 'titulo_pestana' => 'Gestión Académica UES', 'descripcion' => 'Plataforma de gestión universitaria', 'titulo_inicio' => 'Plataforma de gestión universitaria', 'descripcion_inicio' => 'Admisión, expediente, inscripción, notas y asistentes de orientación.', 'texto_pie' => 'Entorno de demostración'],
            'assets' => $assets,
            'color_primario' => '#8B1E2D',
        ]);
    }

    private function logoPng(): string
    {
        $img = imagecreatetruecolor(160, 160);
        $fondo = imagecolorallocate($img, 139, 30, 45);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, 160, 160, $fondo);
        imagefilledellipse($img, 80, 80, 130, 130, $blanco);
        imagefilledellipse($img, 80, 80, 110, 110, $fondo);
        imagestring($img, 5, 62, 72, 'UES', $blanco);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
