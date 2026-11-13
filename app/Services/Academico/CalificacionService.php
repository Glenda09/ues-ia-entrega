<?php

namespace App\Services\Academico;

use App\Models\Academico\ActaGrupo;
use App\Models\Academico\AsignacionDocente;
use App\Models\Academico\CicloCarrera;
use App\Models\Academico\Docente;
use App\Models\Academico\EscalaCalificacion;
use App\Models\Academico\EvaluacionGrupo;
use App\Models\Academico\Grupo;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\NotaEvaluacion;
use App\Models\Academico\PensumMateria;
use App\Models\Academico\ResultadoFinal;
use App\Support\Academico\Actor;
use App\Support\Academico\DominioException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOC-01 y DOC-02: evaluaciones, notas, cálculo final decimal y publicación de actas.
 */
class CalificacionService
{
    private const ESCALA = 8;

    public function __construct(private CalendarioService $calendario, private ConfiguracionService $configuracion, private AuditoriaService $auditoria) {}

    /**
     * Grupo con asignación vigente del docente activo del actor o 404.
     */
    public function grupoAsignado(Actor $actor, int $idGrupo): Grupo
    {
        $docente = Docente::query()->where('id_usuario', $actor->idUsuario)->where('estado', 'activo')->first();
        $ok = $docente && AsignacionDocente::query()->where('id_grupo', $idGrupo)->where('id_docente', $docente->id_docente)->whereNull('hasta')->exists();
        if (! $ok) {
            throw DominioException::noEncontrado();
        }

        return Grupo::query()->with('oferta.ciclo', 'oferta.materia')->findOrFail($idGrupo);
    }

    /**
     * Guarda la estructura de evaluación. Inmutable con notas capturadas.
     *
     * @param  array<int, array{nombre:string, ponderacion:numeric}>  $evaluaciones
     */
    public function guardarEstructura(Grupo $grupo, array $evaluaciones, string $actor): array
    {
        return DB::transaction(function () use ($grupo, $evaluaciones, $actor) {
            $grupo = Grupo::query()->lockForUpdate()->findOrFail($grupo->id_grupo);
            $acta = $this->actaBorrador($grupo);
            if (NotaEvaluacion::query()->whereIn('id_evaluacion', EvaluacionGrupo::query()->where('id_grupo', $grupo->id_grupo)->select('id_evaluacion'))->exists()) {
                throw DominioException::conflicto('ESTRUCTURA_CON_NOTAS', 'La estructura de evaluación tiene notas y no puede modificarse.');
            }
            $escala = EscalaCalificacion::findOrFail($acta->id_escala);
            $total = '0';
            foreach ($evaluaciones as $i => $e) {
                if (bccomp((string) $e['ponderacion'], '0', self::ESCALA) <= 0) {
                    throw ValidationException::withMessages(["evaluaciones.$i.ponderacion" => 'La ponderación debe ser positiva.']);
                }
                $total = bcadd($total, (string) $e['ponderacion'], self::ESCALA);
            }
            if (bccomp($total, (string) $escala->ponderacion_total, self::ESCALA) !== 0) {
                throw ValidationException::withMessages(['evaluaciones' => "La suma de ponderaciones ($total) debe ser igual a {$escala->ponderacion_total}."]);
            }
            EvaluacionGrupo::query()->where('id_grupo', $grupo->id_grupo)->delete();
            foreach (array_values($evaluaciones) as $i => $e) {
                EvaluacionGrupo::create(['id_grupo' => $grupo->id_grupo, 'id_escala' => $escala->id_escala, 'nombre' => $e['nombre'], 'ponderacion' => $e['ponderacion'], 'orden' => $i + 1]);
            }
            $acta->update(['version' => $acta->version + 1]);
            $this->auditoria->registrar($actor, 'evaluaciones.guardar', 'grupo', $grupo->id_grupo, null, ['cantidad' => count($evaluaciones)]);

            return $this->cuadricula($grupo->fresh());
        });
    }

    /**
     * Guarda notas por lote atómico con versión optimista por nota. Errores por inscripción.
     *
     * @param  array<int, array{id_evaluacion:int, id_inscripcion:int, valor:?numeric, condicion?:string, version?:int}>  $notas
     */
    public function guardarNotas(Actor $actor, Grupo $grupo, array $notas): array
    {
        return DB::transaction(function () use ($actor, $grupo, $notas) {
            $ciclo = CicloCarrera::query()->lockForUpdate()->findOrFail($grupo->oferta->id_ciclo_carrera);
            $grupo = Grupo::query()->lockForUpdate()->findOrFail($grupo->id_grupo);
            $acta = ActaGrupo::query()->where('id_grupo', $grupo->id_grupo)->lockForUpdate()->first() ?? $this->actaBorrador($grupo);
            if ($acta->estado === 'publicada') {
                throw DominioException::conflicto('ACTA_PUBLICADA', 'El acta está publicada; las notas no pueden editarse.');
            }
            if ($grupo->estado !== 'publicado' || $ciclo->estado !== 'publicado') {
                throw DominioException::conflicto('PERIODO_CERRADO', 'El grupo o ciclo no está publicado.');
            }
            $this->calendario->exigirVentana($ciclo, 'notas', $actor->permisos);
            $escala = EscalaCalificacion::findOrFail($acta->id_escala);
            $evaluaciones = EvaluacionGrupo::query()->where('id_grupo', $grupo->id_grupo)->pluck('id_evaluacion')->map(fn ($i) => (int) $i)->all();
            $inscripciones = Inscripcion::query()->where('id_grupo', $grupo->id_grupo)->where('estado', 'activa')->orderBy('id_inscripcion')->lockForUpdate()->pluck('id_inscripcion')->map(fn ($i) => (int) $i)->all();
            $errores = [];
            foreach ($notas as $i => $n) {
                $idE = (int) $n['id_evaluacion'];
                $idI = (int) $n['id_inscripcion'];
                $cond = $n['condicion'] ?? 'nota';
                if (! in_array($idE, $evaluaciones, true) || ! in_array($idI, $inscripciones, true)) {
                    $errores["notas.$i"] = ['id_inscripcion' => $idI, 'message' => 'Evaluación o inscripción no pertenece al grupo.'];

                    continue;
                }
                if ($cond === 'nota' && $n['valor'] !== null && (bccomp((string) $n['valor'], (string) $escala->minimo, self::ESCALA) < 0 || bccomp((string) $n['valor'], (string) $escala->maximo, self::ESCALA) > 0)) {
                    $errores["notas.$i"] = ['id_inscripcion' => $idI, 'message' => "La nota debe estar entre {$escala->minimo} y {$escala->maximo}."];

                    continue;
                }
                $existente = NotaEvaluacion::query()->where('id_evaluacion', $idE)->where('id_inscripcion', $idI)->lockForUpdate()->first();
                if ($existente && (int) ($n['version'] ?? 0) !== (int) $existente->version) {
                    $errores["notas.$i"] = ['id_inscripcion' => $idI, 'message' => 'La nota fue modificada en otra sesión.', 'code' => 'VERSION_CONFLICTO', 'version_actual' => (int) $existente->version];

                    continue;
                }
                if (! $existente && (int) ($n['version'] ?? 0) !== 0) {
                    $errores["notas.$i"] = ['id_inscripcion' => $idI, 'message' => 'La nota no existe; envía version 0.', 'code' => 'VERSION_CONFLICTO'];

                    continue;
                }
                $valores = ['valor' => $cond === 'ausente' ? null : $n['valor'], 'condicion' => $cond, 'id_usuario' => $actor->idUsuario];
                if ($existente) {
                    $existente->update($valores + ['version' => $existente->version + 1]);
                } else {
                    NotaEvaluacion::create(['id_evaluacion' => $idE, 'id_inscripcion' => $idI] + $valores);
                }
            }
            if ($errores) {
                throw new DominioException('NOTAS_INVALIDAS', 'El lote contiene errores; no se guardó ninguna nota.', 422, ['errores' => array_values($errores)]);
            }
            $acta->update(['version' => $acta->version + 1, 'calculado_en' => null]);
            $this->auditoria->registrar($actor->idUsuario, 'notas.guardar', 'grupo', $grupo->id_grupo, null, ['cantidad' => count($notas)]);

            return $this->cuadricula($grupo);
        });
    }

    /**
     * Calcula resultados (sin persistirlos) y marca el acta como calculada.
     */
    public function prepararCalculo(Grupo $grupo, string $actor): array
    {
        return DB::transaction(function () use ($grupo, $actor) {
            $acta = ActaGrupo::query()->where('id_grupo', $grupo->id_grupo)->lockForUpdate()->firstOrFail();
            $calculo = $this->calcular($grupo, $acta);
            if ($acta->estado === 'borrador') {
                $acta->update(['calculado_en' => $calculo['completo'] ? now() : null]);
            }
            $this->auditoria->registrar($actor, 'acta.calcular', 'acta_grupo', $acta->id_acta, null, ['completo' => $calculo['completo']]);

            return $calculo + ['version_acta' => (int) $acta->version, 'estado_acta' => $acta->estado];
        });
    }

    /**
     * Publica el acta: revalida ventana, integridad y escala; persiste resultados. Reintento devuelve el acta publicada.
     */
    public function publicar(Actor $actor, Grupo $grupo, int $version): ActaGrupo
    {
        return DB::transaction(function () use ($actor, $grupo, $version) {
            $ciclo = CicloCarrera::query()->lockForUpdate()->findOrFail($grupo->oferta->id_ciclo_carrera);
            $grupo = Grupo::query()->lockForUpdate()->findOrFail($grupo->id_grupo);
            $acta = ActaGrupo::query()->where('id_grupo', $grupo->id_grupo)->lockForUpdate()->first();
            if (! $acta) {
                throw DominioException::conflicto('ACTA_CONFLICTO', 'El grupo no tiene evaluaciones configuradas.');
            }
            if ($acta->estado === 'publicada') {
                if ((int) $acta->version === $version) {
                    return $acta;
                }
                throw DominioException::conflicto('ACTA_CONFLICTO', 'El acta ya fue publicada con otra versión.');
            }
            if ((int) $acta->version !== $version) {
                throw DominioException::conflicto('ACTA_CONFLICTO', 'Las notas cambiaron después del cálculo. Recalcula antes de publicar.', ['version_actual' => (int) $acta->version]);
            }
            $this->calendario->exigirVentana($ciclo, 'notas', $actor->permisos);
            Inscripcion::query()->where('id_grupo', $grupo->id_grupo)->orderBy('id_inscripcion')->lockForUpdate()->get();
            $calculo = $this->calcular($grupo, $acta);
            if (! $calculo['completo']) {
                throw DominioException::conflicto('ACTA_INCOMPLETA', 'Hay notas faltantes; no se puede publicar.', ['pendientes' => $calculo['pendientes']]);
            }
            $escala = EscalaCalificacion::findOrFail($acta->id_escala);
            foreach ($calculo['resultados'] as $r) {
                ResultadoFinal::create([
                    'id_inscripcion' => $r['id_inscripcion'], 'id_acta' => $acta->id_acta, 'nota_final' => $r['nota_final'], 'condicion' => $r['condicion'],
                    'creditos' => $r['condicion'] === 'aprobada' ? $r['creditos'] : 0, 'id_escala' => $escala->id_escala,
                    'version_politica' => $escala->version_politica, 'detalle' => $r['contribuciones'],
                ]);
            }
            $acta->update(['estado' => 'publicada', 'publicado_por' => $actor->idUsuario, 'publicado_en' => now()]);
            $this->auditoria->registrar($actor->idUsuario, 'acta.publicar', 'acta_grupo', $acta->id_acta, null, ['resultados' => count($calculo['resultados'])]);

            return $acta->fresh();
        });
    }

    /**
     * Cálculo decimal: nota = sum(nota × ponderación) / total; redondeo único al final.
     *
     * @return array{completo: bool, pendientes: array, resultados: array}
     */
    public function calcular(Grupo $grupo, ActaGrupo $acta): array
    {
        $escala = EscalaCalificacion::findOrFail($acta->id_escala);
        $evals = EvaluacionGrupo::query()->where('id_grupo', $grupo->id_grupo)->orderBy('orden')->get();
        $inscripciones = Inscripcion::query()->where('id_grupo', $grupo->id_grupo)->where('estado', 'activa')->with('expediente.estudiante')->orderBy('id_inscripcion')->get();
        $notas = NotaEvaluacion::query()->whereIn('id_evaluacion', $evals->pluck('id_evaluacion'))->get()->groupBy('id_inscripcion');
        $creditos = PensumMateria::query()->where('id_materia', $grupo->oferta->id_materia)->pluck('creditos', 'id_pensum');
        $resultados = [];
        $pendientes = [];
        if ($evals->isEmpty()) {
            return ['completo' => false, 'pendientes' => [['message' => 'Sin estructura de evaluación.']], 'resultados' => []];
        }
        foreach ($inscripciones as $ins) {
            $suma = '0';
            $contrib = [];
            $falta = false;
            foreach ($evals as $e) {
                $n = ($notas[$ins->id_inscripcion] ?? collect())->firstWhere('id_evaluacion', $e->id_evaluacion);
                if (! $n || ($n->condicion === 'nota' && $n->valor === null)) {
                    $falta = true;
                    $pendientes[] = ['id_inscripcion' => $ins->id_inscripcion, 'evaluacion' => $e->nombre];

                    continue;
                }
                if ($n->condicion === 'ausente') {
                    if ($escala->ausencia === 'bloquear') {
                        $falta = true;
                        $pendientes[] = ['id_inscripcion' => $ins->id_inscripcion, 'evaluacion' => $e->nombre, 'motivo' => 'ausencia bloquea'];

                        continue;
                    }
                    $valor = (string) $escala->minimo;
                } else {
                    $valor = (string) $n->valor;
                }
                $aporte = bcmul($valor, (string) $e->ponderacion, self::ESCALA);
                $suma = bcadd($suma, $aporte, self::ESCALA);
                $contrib[] = ['evaluacion' => $e->nombre, 'ponderacion' => (float) $e->ponderacion, 'valor' => (float) $valor, 'aporte' => (float) bcdiv($aporte, (string) $escala->ponderacion_total, self::ESCALA)];
            }
            if ($falta) {
                continue;
            }
            $final = self::redondear(bcdiv($suma, (string) $escala->ponderacion_total, self::ESCALA), (int) $escala->precision, $escala->redondeo);
            $aprobada = bccomp($final, (string) $escala->umbral_aprobacion, self::ESCALA) >= 0;
            $resultados[] = [
                'id_inscripcion' => $ins->id_inscripcion,
                'carnet' => $ins->expediente->estudiante->carnet,
                'nota_final' => $final,
                'condicion' => $aprobada ? 'aprobada' : 'reprobada',
                'creditos' => (float) ($creditos[$ins->expediente->id_pensum] ?? 0),
                'contribuciones' => $contrib,
            ];
        }

        return ['completo' => $pendientes === [] && $inscripciones->isNotEmpty(), 'pendientes' => $pendientes, 'resultados' => $resultados];
    }

    /**
     * Redondeo decimal: half_up, half_even o down.
     */
    public static function redondear(string $valor, int $precision, string $modo): string
    {
        $factor = bcpow('10', (string) $precision, 0);
        $escalado = bcmul($valor, $factor, self::ESCALA);
        $negativo = bccomp($escalado, '0', self::ESCALA) < 0;
        $abs = $negativo ? bcmul($escalado, '-1', self::ESCALA) : $escalado;
        $entero = bcadd($abs, '0', 0);
        $fraccion = bcsub($abs, $entero, self::ESCALA);
        $cmp = bccomp($fraccion, '0.5', self::ESCALA);
        $subir = match ($modo) {
            'half_up' => $cmp >= 0,
            'half_even' => $cmp > 0 || ($cmp === 0 && ((int) substr($entero, -1)) % 2 === 1),
            default => false,
        };
        if ($subir) {
            $entero = bcadd($entero, '1', 0);
        }
        $r = bcdiv($entero, $factor, $precision);

        return $negativo ? '-'.$r : $r;
    }

    /**
     * Acta en borrador del grupo, fijando la escala vigente al comenzar evaluaciones.
     */
    public function actaBorrador(Grupo $grupo): ActaGrupo
    {
        $acta = ActaGrupo::query()->where('id_grupo', $grupo->id_grupo)->first();
        if ($acta) {
            return $acta;
        }
        $ciclo = $grupo->oferta->ciclo;
        $idEscala = data_get($ciclo->configuracion_aplicada, 'id_escala') ?: $this->configuracion->valor('calificaciones.id_escala_vigente');
        if (! $idEscala) {
            throw DominioException::configuracionIncompleta(['calificaciones.id_escala_vigente']);
        }

        return ActaGrupo::create(['id_grupo' => $grupo->id_grupo, 'id_escala' => $idEscala, 'estado' => 'borrador']);
    }

    /**
     * Cuadrícula de evaluaciones y notas del grupo.
     *
     * @return array<string, mixed>
     */
    public function cuadricula(Grupo $grupo): array
    {
        $acta = ActaGrupo::query()->where('id_grupo', $grupo->id_grupo)->first();
        $escala = $acta ? EscalaCalificacion::find($acta->id_escala) : $this->configuracion->escalaVigente();
        $evals = EvaluacionGrupo::query()->where('id_grupo', $grupo->id_grupo)->orderBy('orden')->get();
        $ins = Inscripcion::query()->where('id_grupo', $grupo->id_grupo)->where('estado', 'activa')->with('expediente.estudiante')->orderBy('id_inscripcion')->get();
        $notas = NotaEvaluacion::query()->whereIn('id_evaluacion', $evals->pluck('id_evaluacion'))->get();
        $ventana = $this->calendario->estadoVentana($grupo->oferta->ciclo, 'notas');

        return [
            'id_grupo' => $grupo->id_grupo,
            'escala' => $escala?->only(['id_escala', 'codigo', 'version_politica', 'minimo', 'maximo', 'umbral_aprobacion', 'precision', 'redondeo', 'ponderacion_total', 'ausencia']),
            'acta' => $acta ? ['estado' => $acta->estado, 'version' => (int) $acta->version, 'calculado_en' => $acta->calculado_en?->toIso8601String(), 'publicado_en' => $acta->publicado_en?->toIso8601String()] : null,
            'ventana_notas' => $ventana,
            'estructura_bloqueada' => $notas->isNotEmpty(),
            'evaluaciones' => $evals->map->only(['id_evaluacion', 'nombre', 'ponderacion', 'orden'])->values(),
            'estudiantes' => $ins->map(fn ($i) => [
                'id_inscripcion' => $i->id_inscripcion,
                'carnet' => $i->expediente->estudiante->carnet,
                'nombre' => trim(($i->expediente->estudiante->nombre_uso ?: $i->expediente->estudiante->nombres).' '.$i->expediente->estudiante->primer_apellido),
                'notas' => $notas->where('id_inscripcion', $i->id_inscripcion)->map->only(['id_evaluacion', 'valor', 'condicion', 'version'])->values(),
            ])->values(),
        ];
    }
}
