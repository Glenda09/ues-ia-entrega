<?php

namespace App\Services\Academico;

use App\Models\Academico\CicloCarrera;
use App\Models\Academico\Expediente;
use App\Models\Academico\Grupo;
use App\Models\Academico\HorarioGrupo;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\PensumEquivalencia;
use App\Models\Academico\PensumMateria;
use App\Models\Academico\PensumRequisito;
use App\Support\Academico\Actor;
use App\Support\Academico\DominioException;
use App\Support\Academico\Reloj;
use App\Support\Academico\Ventana;

/**
 * INS-01: motor compartido de elegibilidad (portal, inscripción e IA).
 *
 * Política inicial D04: prerrequisitos simples (AND) aprobados con resultado publicado,
 * repetición de reprobadas, exclusión de aprobadas, choque de horario y carga configurados.
 */
class ElegibilidadService
{
    public function __construct(
        private CalendarioService $calendario,
        private ConfiguracionService $configuracion,
        private HistorialAcademicoService $historial,
        private OfertaService $oferta,
        private Reloj $reloj,
    ) {}

    /**
     * Expediente propio del actor o 404.
     */
    public function expedientePropio(Actor $actor, int $idExpediente): Expediente
    {
        $exp = Expediente::query()->whereKey($idExpediente)
            ->whereHas('estudiante', fn ($q) => $q->where('id_usuario', $actor->idUsuario))->first();
        if (! $exp) {
            throw DominioException::noEncontrado();
        }

        return $exp;
    }

    /**
     * Ciclo de la carrera del expediente o 404.
     */
    public function cicloDe(Expediente $exp, int $idCiclo): CicloCarrera
    {
        $ciclo = CicloCarrera::query()->whereKey($idCiclo)->where('id_carrera', $exp->id_carrera)->where('estado', '!=', 'borrador')->first();
        if (! $ciclo) {
            throw DominioException::noEncontrado();
        }

        return $ciclo;
    }

    /**
     * Oferta elegible con razones por materia.
     *
     * @param  array<int, int>  $seleccion  Grupos propuestos para validación conjunta (opcional).
     * @return array<string, mixed>
     */
    public function evaluar(Actor $actor, Expediente $exp, CicloCarrera $ciclo, array $seleccion = []): array
    {
        $ahora = $this->reloj->ahora();
        $ventana = $this->calendario->estadoVentana($ciclo, 'inscripcion', $ahora);
        $permisosGracia = (array) data_get($ciclo->configuracion_aplicada, 'aplicacion.permisos_gracia_inscripcion', []);
        $conf = (array) (data_get($ciclo->configuracion_aplicada, 'inscripcion') ?: $this->configuracion->grupo('inscripcion'));
        $generales = [];
        if ($exp->estado !== ExpedienteEstados::ACTIVO) {
            $generales[] = ['code' => 'ESTADO_NO_HABILITADO', 'message' => 'El expediente no está Activo ('.(ExpedienteEstados::ETIQUETAS[$exp->estado] ?? $exp->estado).').'];
        }
        if ($ciclo->estado !== 'publicado' || ! Ventana::puedeOperar($ventana['estado'], $actor->permisos, $permisosGracia)) {
            $generales[] = ['code' => 'PERIODO_CERRADO', 'message' => 'El periodo de inscripción no está abierto ('.$ventana['estado'].').'];
        }

        $pensumMaterias = PensumMateria::query()->where('id_pensum', $exp->id_pensum)->with('materia')->get()->keyBy('id_materia');
        $requisitos = PensumRequisito::query()->where('id_pensum', $exp->id_pensum)->get();
        $equivalencias = ! empty($conf['equivalencias_activas']) ? PensumEquivalencia::query()->where('id_pensum', $exp->id_pensum)->get() : collect();
        $aprobadas = $this->materiasAcreditadas($exp->id_expediente, $equivalencias);
        $intentos = $this->historial->intentos($exp->id_expediente);
        $activas = Inscripcion::query()->where('id_expediente', $exp->id_expediente)->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)->where('estado', 'activa')->get()->keyBy('id_materia');

        $cargaActual = $activas->sum(fn ($i) => (float) ($pensumMaterias[$i->id_materia]->creditos ?? 0));
        $cargaMax = $conf['carga_maxima_creditos'] ?? null;
        $validarChoque = (bool) ($conf['validar_choque_horario'] ?? true);
        $horariosActivos = HorarioGrupo::query()->whereIn('id_grupo', $activas->pluck('id_grupo'))->get()->groupBy('id_grupo');

        $grupos = Grupo::query()->whereHas('oferta', fn ($q) => $q->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera))
            ->where('estado', 'publicado')->with('oferta')->orderBy('id_grupo')->get()->groupBy(fn ($g) => $g->oferta->id_materia);

        $materias = [];
        foreach ($grupos as $idMateria => $gs) {
            $pm = $pensumMaterias[$idMateria] ?? null;
            $razones = $generales;
            if (! $pm) {
                $razones[] = ['code' => 'MATERIA_FUERA_PENSUM', 'message' => 'La materia no pertenece a tu pensum asignado.'];
            } else {
                if (in_array((int) $idMateria, $aprobadas, true)) {
                    $razones[] = ['code' => 'MATERIA_YA_APROBADA', 'message' => 'Ya aprobaste esta materia.'];
                }
                $reprobada = $intentos->where('id_materia', $idMateria)->where('condicion', 'reprobada')->isNotEmpty();
                if ($reprobada && empty($conf['permitir_repetir_reprobadas']) && ! in_array((int) $idMateria, $aprobadas, true)) {
                    $razones[] = ['code' => 'REPETICION_NO_PERMITIDA', 'message' => 'La configuración no permite repetir materias reprobadas.'];
                }
                $pendientes = $requisitos->where('id_materia', $idMateria)->where('tipo', 'prerrequisito')
                    ->reject(fn ($r) => in_array((int) $r->id_materia_requisito, $aprobadas, true));
                foreach ($pendientes as $r) {
                    $razones[] = ['code' => 'PRERREQUISITO_PENDIENTE', 'message' => 'Requiere aprobar '.($pensumMaterias[$r->id_materia_requisito]->materia->codigo ?? 'otra materia').'.', 'id_materia' => (int) $r->id_materia_requisito];
                }
                if (! empty($conf['correquisitos_activos'])) {
                    foreach ($requisitos->where('id_materia', $idMateria)->where('tipo', 'correquisito') as $r) {
                        $cumple = in_array((int) $r->id_materia_requisito, $aprobadas, true) || $activas->has($r->id_materia_requisito)
                            || collect($seleccion)->contains(fn ($g) => (int) (Grupo::find($g)?->oferta->id_materia) === (int) $r->id_materia_requisito);
                        if (! $cumple) {
                            $razones[] = ['code' => 'CORREQUISITO_PENDIENTE', 'message' => 'Requiere cursar en conjunto '.($pensumMaterias[$r->id_materia_requisito]->materia->codigo ?? '').'.'];
                        }
                    }
                }
                if ($activas->has($idMateria)) {
                    $razones[] = ['code' => 'MATERIA_YA_INSCRITA', 'message' => 'Ya tienes inscrita esta materia en el ciclo.', 'id_grupo' => (int) $activas[$idMateria]->id_grupo];
                }
                if ($cargaMax !== null && ! $activas->has($idMateria) && $cargaActual + (float) $pm->creditos > (float) $cargaMax) {
                    $razones[] = ['code' => 'CARGA_EXCEDIDA', 'message' => 'Excede la carga máxima de '.$cargaMax.' créditos.'];
                }
            }
            $gruposJson = [];
            foreach ($gs as $g) {
                $json = $this->oferta->grupoJson($g);
                $razonesGrupo = [];
                if ($json['disponibles'] <= 0) {
                    $razonesGrupo[] = ['code' => 'CUPO_AGOTADO', 'message' => 'Sin cupo disponible.'];
                }
                if ($validarChoque) {
                    $propios = $json['horarios']->toArray();
                    foreach ($horariosActivos as $idG => $hs) {
                        if ((int) $idG !== (int) $g->id_grupo && (int) ($activas[$idMateria]->id_grupo ?? 0) !== (int) $idG && OfertaService::chocan($propios, $hs->toArray())) {
                            $razonesGrupo[] = ['code' => 'CHOQUE_HORARIO', 'message' => 'Choca con otra materia inscrita.'];
                            break;
                        }
                    }
                }
                $json['razones'] = $razonesGrupo;
                $json['inscrito'] = (int) ($activas[$idMateria]->id_grupo ?? 0) === (int) $g->id_grupo;
                $json['puede_inscribir'] = $razones === [] && $razonesGrupo === [];
                $gruposJson[] = $json;
            }
            $materias[] = [
                'id_materia' => (int) $idMateria,
                'codigo' => $pm?->materia->codigo ?? $gs->first()->oferta->materia->codigo,
                'nombre' => $pm?->materia->nombre ?? $gs->first()->oferta->materia->nombre,
                'nivel' => $pm?->nivel,
                'creditos' => $pm ? (float) $pm->creditos : null,
                'intentos' => $intentos->where('id_materia', $idMateria)->count(),
                'razones' => $razones,
                'puede_inscribir' => $razones === [] && collect($gruposJson)->contains('puede_inscribir', true),
                'grupos' => $gruposJson,
            ];
        }
        usort($materias, fn ($a, $b) => [$a['nivel'] ?? 99, $a['codigo']] <=> [$b['nivel'] ?? 99, $b['codigo']]);

        $resultado = [
            'id_expediente' => $exp->id_expediente,
            'id_ciclo' => $ciclo->id_ciclo_carrera,
            'ciclo' => $ciclo->only(['codigo', 'nombre', 'estado']),
            'calculado_en' => $ahora->toIso8601String(),
            'version_calendario' => (int) $ciclo->version,
            'ventana' => $ventana,
            'razones_generales' => $generales,
            'carga' => ['actual' => $cargaActual, 'maxima' => $cargaMax],
            'materias' => $materias,
        ];
        if ($seleccion) {
            $resultado['seleccion'] = $this->validarSeleccion($resultado, $seleccion, $pensumMaterias, $activas, $horariosActivos, $cargaActual, $cargaMax, $validarChoque);
        }

        return $resultado;
    }

    /**
     * Validación conjunta de un conjunto de grupos (sin escribir).
     *
     * @return array{valida: bool, errores: array<int, array<string, mixed>>, carga_final: float}
     */
    private function validarSeleccion(array $eval, array $seleccion, $pensumMaterias, $activas, $horariosActivos, float $cargaActual, $cargaMax, bool $validarChoque): array
    {
        $errores = [];
        $porGrupo = [];
        foreach ($eval['materias'] as $m) {
            foreach ($m['grupos'] as $g) {
                $porGrupo[$g['id_grupo']] = ['materia' => $m, 'grupo' => $g];
            }
        }
        $materiasSel = [];
        $horariosSel = [];
        $carga = $cargaActual;
        foreach (array_values(array_unique($seleccion)) as $idGrupo) {
            $info = $porGrupo[$idGrupo] ?? null;
            if (! $info) {
                $errores[] = ['id_grupo' => $idGrupo, 'code' => 'GRUPO_NO_DISPONIBLE', 'message' => 'El grupo no está disponible en el ciclo.'];

                continue;
            }
            $m = $info['materia'];
            $g = $info['grupo'];
            if (isset($materiasSel[$m['id_materia']])) {
                $errores[] = ['id_grupo' => $idGrupo, 'code' => 'MATERIA_DUPLICADA', 'message' => 'Seleccionaste dos grupos de la misma materia.'];

                continue;
            }
            $materiasSel[$m['id_materia']] = true;
            $mismoGrupo = $activas->has($m['id_materia']) && (int) $activas[$m['id_materia']]->id_grupo === (int) $idGrupo;
            if ($mismoGrupo) {
                continue; // equivalente
            }
            $cambio = $activas->has($m['id_materia']);
            $razonesMateria = collect($m['razones'])->reject(fn ($r) => $cambio && in_array($r['code'], ['MATERIA_YA_INSCRITA', 'CARGA_EXCEDIDA'], true))->values();
            $razonesGrupo = collect($g['razones'])->reject(fn ($r) => $r['code'] === 'CHOQUE_HORARIO')->values();
            foreach ($razonesMateria->merge($razonesGrupo) as $r) {
                $errores[] = ['id_grupo' => $idGrupo] + $r;
            }
            if (! $cambio) {
                $carga += (float) ($m['creditos'] ?? 0);
            }
            $propios = collect($g['horarios'])->toArray();
            if ($validarChoque) {
                foreach ($horariosActivos as $idG => $hs) {
                    $reemplazado = $cambio && (int) $activas[$m['id_materia']]->id_grupo === (int) $idG;
                    if (! $reemplazado && OfertaService::chocan($propios, $hs->toArray())) {
                        $errores[] = ['id_grupo' => $idGrupo, 'code' => 'CHOQUE_HORARIO', 'message' => 'Choca con otra materia inscrita.'];
                        break;
                    }
                }
                foreach ($horariosSel as $otro => $hs) {
                    if (OfertaService::chocan($propios, $hs)) {
                        $errores[] = ['id_grupo' => $idGrupo, 'code' => 'CHOQUE_HORARIO', 'message' => 'Choca con otra materia de la selección.'];
                        break;
                    }
                }
            }
            $horariosSel[$idGrupo] = $propios;
        }
        if ($cargaMax !== null && $carga > (float) $cargaMax) {
            $errores[] = ['code' => 'CARGA_EXCEDIDA', 'message' => 'La selección excede la carga máxima de '.$cargaMax.' créditos.'];
        }

        return ['valida' => $errores === [], 'errores' => $errores, 'carga_final' => $carga];
    }

    /**
     * Materias acreditadas: aprobadas publicadas + destinos de equivalencias activas (no transitivas).
     *
     * @return array<int, int>
     */
    public function materiasAcreditadas(int $idExpediente, $equivalencias = null): array
    {
        $aprobadas = $this->historial->materiasAprobadas($idExpediente);
        foreach ($equivalencias ?? [] as $e) {
            if (in_array((int) $e->id_materia_origen, $aprobadas, true)) {
                $aprobadas[] = (int) $e->id_materia_destino;
            }
        }

        return array_values(array_unique($aprobadas));
    }
}
