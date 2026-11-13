<?php

namespace Tests\Feature\Academico;

use App\Models\Academico\Carrera;
use App\Models\Academico\EscalaCalificacion;
use App\Models\Academico\Estudiante;
use App\Models\Academico\Expediente;
use App\Models\Academico\GestorCarrera;
use App\Models\Academico\Materia;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use App\Services\Academico\CalendarioService;
use App\Services\Academico\CatalogoService;
use App\Services\Academico\ConfiguracionService;
use App\Services\Academico\OfertaService;
use App\Services\Academico\PensumService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Utilidades de pruebas académicas: actores con perfiles, cabeceras JWT y datos base.
 */
trait AcademicoHelpers
{
    protected function usuario(array $perfiles = [], ?string $email = null): AuthUsuario
    {
        $email ??= Str::lower(Str::random(10)).'@test.ues';
        $u = AuthUsuario::create([
            'username' => Str::before($email, '@'),
            'email' => $email,
            'password' => 'password123',
            'fecha_verificacion' => now(),
            'id_estado' => 'ACTIVO',
        ]);
        DB::table('auth_identificador')->insertOrIgnore([
            ['clave' => $email, 'id_usuario' => $u->id_usuario, 'created_at' => now(), 'updated_at' => now()],
            ['clave' => Str::before($email, '@'), 'id_usuario' => $u->id_usuario, 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ($perfiles as $p) {
            AuthUsuarioPerfil::create(['id_usuario' => $u->id_usuario, 'codigo_perfil' => $p, 'creado_por' => $u->id_usuario]);
        }
        Cache::flush();

        return $u;
    }

    protected function headers(AuthUsuario $u): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($u), 'Accept' => 'application/json'];
    }

    protected function admin(): AuthUsuario
    {
        return $this->usuario(['academico_admin']);
    }

    protected function carrera(string $codigo = 'ING-SIS', array $extra = []): Carrera
    {
        return Carrera::create(array_merge(['codigo' => $codigo, 'nombre' => 'Carrera '.$codigo, 'estado' => 'activa'], $extra));
    }

    protected function gestorDe(Carrera $carrera, array $perfiles = ['academico_gestor_carrera']): AuthUsuario
    {
        $g = $this->usuario($perfiles);
        GestorCarrera::create(['id_usuario' => $g->id_usuario, 'id_carrera' => $carrera->id_carrera]);

        return $g;
    }

    /**
     * Escala sintética de prueba (DOC-02 fixture): 0–10, aprobación 6, precisión 2, half_up, total 100.
     */
    protected function escalaPrueba(string $ausencia = 'bloquear'): EscalaCalificacion
    {
        $escala = app(ConfiguracionService::class)->crearEscala([
            'codigo' => 'PRUEBA', 'nombre' => 'Escala de prueba 0-10', 'minimo' => 0, 'maximo' => 10,
            'umbral_aprobacion' => 6, 'precision' => 2, 'redondeo' => 'half_up', 'ponderacion_total' => 100, 'ausencia' => $ausencia,
        ], '537b8b56-341c-4322-869a-de00d11cf10e');
        $conf = app(ConfiguracionService::class);
        $conf->actualizar(['calificaciones' => ['id_escala_vigente' => $escala->id_escala]], $conf->revisionActual(), 'Escala de prueba', '537b8b56-341c-4322-869a-de00d11cf10e');

        return $escala;
    }

    /**
     * Escenario académico: carrera, materias M1→M2 (prerrequisito), M3, pensum publicado,
     * docente activo, ciclo publicado con ventanas abiertas alrededor de "ahora" y grupos publicados.
     *
     * @return array<string, mixed>
     */
    protected function escenario(string $codigo = 'ISI', array $opciones = []): array
    {
        Carbon::setTestNow($opciones['ahora'] ?? '2026-03-10 15:00:00');
        $conf = app(ConfiguracionService::class);
        $escala = EscalaCalificacion::query()->where('codigo', 'PRUEBA')->first() ?? $this->escalaPrueba($opciones['ausencia'] ?? 'bloquear');
        $admin = '537b8b56-341c-4322-869a-de00d11cf10e';

        $carrera = $this->carrera($codigo);
        $m = [];
        foreach (['M1' => 'Introducción', 'M2' => 'Avanzada', 'M3' => 'Optativa general'] as $k => $n) {
            $m[$k] = Materia::create(['codigo' => $codigo.'-'.$k, 'nombre' => $n.' '.$codigo]);
        }
        $pensumSvc = app(PensumService::class);
        $pensum = $pensumSvc->crear($carrera, '2026', null, $admin);
        $pensumSvc->actualizarBorrador($pensum, [
            'version' => 1,
            'vigente_desde' => '2026-01-01',
            'materias' => [
                ['id_materia' => $m['M1']->id_materia, 'nivel' => 1, 'creditos' => 4],
                ['id_materia' => $m['M2']->id_materia, 'nivel' => 2, 'creditos' => 4],
                ['id_materia' => $m['M3']->id_materia, 'nivel' => 1, 'creditos' => 3],
            ],
            'requisitos' => [['id_materia' => $m['M2']->id_materia, 'id_materia_requisito' => $m['M1']->id_materia]],
        ], $admin);
        $pensum = $pensumSvc->transicion($pensum->fresh(), 'publicado', 2, $admin);

        $cuentaDocente = $this->usuario();
        [$docente] = app(CatalogoService::class)->crearDocente(['id_usuario' => $cuentaDocente->id_usuario, 'codigo' => 'D-'.$codigo, 'nombre_academico' => 'Docente '.$codigo], $admin);

        $cal = app(CalendarioService::class);
        $ciclo = $cal->crear($carrera, [
            'codigo' => 'CI-2026', 'nombre' => 'Ciclo I 2026',
            'calendario' => $opciones['calendario'] ?? [
                'inicio' => '2026-02-01 00:00', 'fin' => '2026-07-01 00:00',
                'ventanas' => [
                    'inscripcion' => ['inicio' => '2026-03-01 00:00', 'fin_regular' => '2026-03-20 00:00', 'fin_gracia' => '2026-03-22 00:00'],
                    'notas' => ['inicio' => '2026-03-01 00:00', 'fin_regular' => '2026-06-20 00:00', 'fin_gracia' => null],
                ],
            ],
        ], $admin);
        $ciclo = $cal->transicion($ciclo, 'publicado', (int) $ciclo->version, $admin);

        $of = app(OfertaService::class);
        $grupos = [];
        $horarios = ['M1' => [['dia' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']], 'M2' => [['dia' => 2, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']], 'M3' => [['dia' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '12:00']]];
        foreach ($m as $k => $materia) {
            $oferta = $of->crearOferta($ciclo, $materia->id_materia, $admin);
            $g = $of->crearGrupo($oferta, ['codigo' => 'G1', 'cupo' => $opciones['cupo'] ?? 30, 'horarios' => $horarios[$k], 'id_docente' => $docente->id_docente], $admin);
            $grupos[$k] = $of->actualizarGrupo($g, ['version' => 1, 'estado' => 'publicado'], $admin);
        }

        return compact('carrera', 'm', 'pensum', 'docente', 'cuentaDocente', 'ciclo', 'grupos', 'escala') + ['admin' => $admin];
    }

    /**
     * Crea estudiante y expediente activo directamente (para pruebas de módulos posteriores).
     *
     * @return array{0: AuthUsuario, 1: Expediente}
     */
    protected function estudianteEn(array $esc, string $apellido = 'Perez'): array
    {
        $u = $this->usuario(['academico_estudiante']);
        $est = Estudiante::create([
            'id_usuario' => $u->id_usuario, 'carnet' => strtoupper(Str::random(8)), 'carnet_prefijo' => 'PX', 'carnet_anio' => 2026,
            'carnet_correlativo' => random_int(1, 999999), 'carnet_regla' => 'prueba', 'nombres' => 'Ana', 'primer_apellido' => $apellido,
            'segundo_apellido' => 'Lopez', 'email' => $u->email,
        ]);
        $exp = Expediente::create([
            'id_estudiante' => $est->id_estudiante, 'id_carrera' => $esc['carrera']->id_carrera, 'id_pensum' => $esc['pensum']->id_pensum,
            'fecha_ingreso' => '2026-01-15', 'estado' => 'ACTIVO',
        ]);

        return [$u, $exp];
    }
}
