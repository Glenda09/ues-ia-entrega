<?php

namespace App\Services\Academico;

use App\Models\Academico\CicloCarrera;
use App\Models\Academico\Expediente;
use App\Models\Academico\Grupo;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\InscripcionEvento;
use App\Support\Academico\Actor;
use App\Support\Academico\DominioException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * INS-02: confirmación incremental de grupos, cambio de grupo y retiro.
 *
 * Orden de bloqueo común: ciclo → expediente → grupos (ID asc) → actas → inscripciones.
 */
class InscripcionService
{
    public function __construct(private ElegibilidadService $elegibilidad, private AuditoriaService $auditoria) {}

    /**
     * Confirma un conjunto de grupos en una transacción (todo o nada).
     *
     * @param  array<int, int>  $grupos
     * @return array<int, Inscripcion>
     */
    public function confirmar(Actor $actor, Expediente $exp, CicloCarrera $ciclo, array $grupos): array
    {
        return $this->conReintento(function () use ($actor, $exp, $ciclo, $grupos) {
            return DB::transaction(function () use ($actor, $exp, $ciclo, $grupos) {
                $ciclo = CicloCarrera::query()->lockForUpdate()->findOrFail($ciclo->id_ciclo_carrera);
                $exp = Expediente::query()->lockForUpdate()->findOrFail($exp->id_expediente);
                $ids = collect($grupos)->map(fn ($g) => (int) $g)->unique()->sort()->values();
                $anteriores = Inscripcion::query()->where('id_expediente', $exp->id_expediente)->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)->where('estado', 'activa')->pluck('id_grupo');
                $bloquear = $ids->merge($anteriores)->unique()->sort()->values();
                $gruposBloq = Grupo::query()->whereIn('id_grupo', $bloquear)->orderBy('id_grupo')->lockForUpdate()->get()->keyBy('id_grupo');

                // Revalidación con datos actuales después del bloqueo.
                $eval = $this->elegibilidad->evaluar($actor, $exp, $ciclo, $ids->all());
                if ($eval['razones_generales']) {
                    $r = $eval['razones_generales'][0];
                    throw DominioException::conflicto($r['code'], $r['message'], ['errores' => $eval['razones_generales']]);
                }
                if (! $eval['seleccion']['valida']) {
                    $primero = $eval['seleccion']['errores'][0];
                    throw DominioException::conflicto($primero['code'], $primero['message'], ['errores' => $eval['seleccion']['errores']]);
                }

                $resultado = [];
                foreach ($ids as $idGrupo) {
                    $g = $gruposBloq[$idGrupo];
                    $idMateria = (int) $g->oferta->id_materia;
                    $fila = Inscripcion::query()->where('id_expediente', $exp->id_expediente)->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)->where('id_materia', $idMateria)->lockForUpdate()->first();
                    if ($fila && $fila->estado === 'activa' && (int) $fila->id_grupo === $idGrupo) {
                        $resultado[] = $fila;

                        continue;
                    }
                    $this->exigirCupo($g);
                    if ($fila && $fila->estado === 'activa') {
                        $this->exigirSinNotas($fila);
                        $anterior = $fila->id_grupo;
                        $fila->update(['id_grupo' => $idGrupo, 'version' => $fila->version + 1, 'id_usuario' => $actor->idUsuario]);
                        InscripcionEvento::create(['id_inscripcion' => $fila->id_inscripcion, 'operacion' => 'cambio_grupo', 'id_grupo_anterior' => $anterior, 'id_grupo_nuevo' => $idGrupo, 'id_usuario' => $actor->idUsuario, 'instante' => now()]);
                    } elseif ($fila) {
                        $fila->update(['id_grupo' => $idGrupo, 'estado' => 'activa', 'inscrito_en' => now(), 'retirado_en' => null, 'version' => $fila->version + 1, 'id_usuario' => $actor->idUsuario]);
                        InscripcionEvento::create(['id_inscripcion' => $fila->id_inscripcion, 'operacion' => 'reinscripcion', 'id_grupo_nuevo' => $idGrupo, 'id_usuario' => $actor->idUsuario, 'instante' => now()]);
                    } else {
                        $fila = Inscripcion::create([
                            'id_expediente' => $exp->id_expediente, 'id_ciclo_carrera' => $ciclo->id_ciclo_carrera, 'id_materia' => $idMateria,
                            'id_grupo' => $idGrupo, 'estado' => 'activa', 'inscrito_en' => now(), 'id_usuario' => $actor->idUsuario,
                        ]);
                        InscripcionEvento::create(['id_inscripcion' => $fila->id_inscripcion, 'operacion' => 'alta', 'id_grupo_nuevo' => $idGrupo, 'id_usuario' => $actor->idUsuario, 'instante' => now()]);
                    }
                    $resultado[] = $fila->fresh();
                }
                $this->auditoria->registrar($actor->idUsuario, 'inscripcion.confirmar', 'expediente', $exp->id_expediente, null, ['id_ciclo' => $ciclo->id_ciclo_carrera, 'grupos' => $ids->all()]);

                return $resultado;
            });
        });
    }

    /**
     * Retiro dentro de la ventana. Un segundo retiro equivalente devuelve el estado actual.
     */
    public function retirar(Actor $actor, Inscripcion $ins, string $motivo): Inscripcion
    {
        return $this->conReintento(fn () => DB::transaction(function () use ($actor, $ins, $motivo) {
            $ciclo = CicloCarrera::query()->lockForUpdate()->findOrFail($ins->id_ciclo_carrera);
            $exp = Expediente::query()->lockForUpdate()->findOrFail($ins->id_expediente);
            Grupo::query()->whereKey($ins->id_grupo)->lockForUpdate()->first();
            $ins = Inscripcion::query()->lockForUpdate()->findOrFail($ins->id_inscripcion);
            if ($ins->estado === 'retirada') {
                return $ins;
            }
            if ($exp->estado !== ExpedienteEstados::ACTIVO) {
                throw DominioException::conflicto('ESTADO_NO_HABILITADO', 'El expediente no está Activo.');
            }
            app(CalendarioService::class)->exigirVentana($ciclo, 'inscripcion', $actor->permisos);
            $this->exigirSinNotas($ins);
            $ins->update(['estado' => 'retirada', 'retirado_en' => now(), 'version' => $ins->version + 1, 'id_usuario' => $actor->idUsuario]);
            InscripcionEvento::create(['id_inscripcion' => $ins->id_inscripcion, 'operacion' => 'retiro', 'id_grupo_anterior' => $ins->id_grupo, 'motivo' => $motivo, 'id_usuario' => $actor->idUsuario, 'instante' => now()]);
            $this->auditoria->registrar($actor->idUsuario, 'inscripcion.retirar', 'inscripcion', $ins->id_inscripcion, $motivo);

            return $ins;
        }));
    }

    private function exigirCupo(Grupo $g): void
    {
        $activos = Inscripcion::query()->where('id_grupo', $g->id_grupo)->where('estado', 'activa')->count();
        if ($activos >= (int) $g->cupo) {
            throw DominioException::conflicto('CUPO_AGOTADO', "El grupo {$g->codigo} no tiene cupo disponible.", ['id_grupo' => $g->id_grupo]);
        }
    }

    private function exigirSinNotas(Inscripcion $ins): void
    {
        $acta = DB::table('acta_grupo')->where('id_grupo', $ins->id_grupo)->lockForUpdate()->first();
        $conNotas = DB::table('nota_evaluacion')->where('id_inscripcion', $ins->id_inscripcion)->exists();
        if ($conNotas || ($acta && $acta->estado === 'publicada')) {
            throw DominioException::conflicto('INSCRIPCION_CON_NOTAS', 'La inscripción tiene notas registradas o acta publicada.');
        }
    }

    /**
     * Reintenta ante deadlock (máximo 3); luego CONCURRENCIA_REINTENTAR.
     */
    private function conReintento(callable $fn): mixed
    {
        for ($i = 0; $i < 3; $i++) {
            try {
                return $fn();
            } catch (QueryException $e) {
                if (! in_array($e->getCode(), ['40P01', '40001'], true)) {
                    throw $e;
                }
                usleep(50000 * ($i + 1));
            }
        }
        throw DominioException::conflicto('CONCURRENCIA_REINTENTAR', 'Conflicto de concurrencia, reintenta la operación.');
    }

    /**
     * @return array<string, mixed>
     */
    public function json(Inscripcion $i): array
    {
        $g = $i->grupo;

        return [
            'id_inscripcion' => $i->id_inscripcion,
            'id_expediente' => $i->id_expediente,
            'id_ciclo' => $i->id_ciclo_carrera,
            'materia' => $i->materia?->only(['id_materia', 'codigo', 'nombre']),
            'grupo' => ['id_grupo' => $g?->id_grupo, 'codigo' => $g?->codigo, 'horarios' => $g?->horarios()->get(['dia', 'hora_inicio', 'hora_fin'])],
            'estado' => $i->estado,
            'inscrito_en' => $i->inscrito_en?->toIso8601String(),
            'retirado_en' => $i->retirado_en?->toIso8601String(),
            'eventos' => $i->eventos()->orderBy('id_inscripcion_evento')->get(['operacion', 'id_grupo_anterior', 'id_grupo_nuevo', 'motivo', 'instante']),
        ];
    }
}
