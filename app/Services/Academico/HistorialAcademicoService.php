<?php

namespace App\Services\Academico;

use App\Models\Academico\Expediente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EXP-03: consulta compartida del historial académico por expediente.
 *
 * Solo los resultados de actas publicadas alimentan avance, elegibilidad y certificación.
 */
class HistorialAcademicoService
{
    /**
     * Intentos por inscripción con su resultado publicado (si existe), ordenados por fin de ciclo e ID.
     *
     * @return Collection<int, object>
     */
    public function intentos(int $idExpediente): Collection
    {
        return DB::table('inscripcion as i')
            ->join('ciclo_carrera as c', 'c.id_ciclo_carrera', '=', 'i.id_ciclo_carrera')
            ->join('materia as m', 'm.id_materia', '=', 'i.id_materia')
            ->join('grupo as g', 'g.id_grupo', '=', 'i.id_grupo')
            ->leftJoin('resultado_final as r', 'r.id_inscripcion', '=', 'i.id_inscripcion')
            ->leftJoin('acta_grupo as a', function ($j) {
                $j->on('a.id_acta', '=', 'r.id_acta')->where('a.estado', '=', 'publicada');
            })
            ->where('i.id_expediente', $idExpediente)
            ->orderBy('c.fin')->orderBy('i.id_inscripcion')
            ->get([
                'i.id_inscripcion', 'i.id_materia', 'm.codigo as materia_codigo', 'm.nombre as materia_nombre', 'i.estado as estado_inscripcion',
                'i.id_ciclo_carrera', 'c.codigo as ciclo_codigo', 'c.nombre as ciclo_nombre', 'c.inicio as ciclo_inicio', 'c.fin as ciclo_fin', 'c.estado as ciclo_estado',
                'g.codigo as grupo_codigo', 'i.id_grupo',
                DB::raw('CASE WHEN a.id_acta IS NOT NULL THEN r.nota_final END as nota_final'),
                DB::raw('CASE WHEN a.id_acta IS NOT NULL THEN r.condicion END as condicion'),
                DB::raw('CASE WHEN a.id_acta IS NOT NULL THEN r.creditos END as creditos'),
                DB::raw('CASE WHEN a.id_acta IS NOT NULL THEN r.version_politica END as version_politica'),
                DB::raw('CASE WHEN a.id_acta IS NOT NULL THEN a.publicado_en END as publicado_en'),
            ]);
    }

    /**
     * Resultados publicados (fuente de avance y certificación).
     *
     * @return Collection<int, object>
     */
    public function publicados(int $idExpediente): Collection
    {
        return $this->intentos($idExpediente)->whereNotNull('condicion')->values();
    }

    /**
     * IDs de materias aprobadas con resultado publicado.
     *
     * @return array<int, int>
     */
    public function materiasAprobadas(int $idExpediente): array
    {
        return $this->publicados($idExpediente)->where('condicion', 'aprobada')->pluck('id_materia')->map(fn ($i) => (int) $i)->unique()->values()->all();
    }

    /**
     * Historial combinado: admisión, eventos de estado e intentos clasificados.
     *
     * @return array<string, mixed>
     */
    public function historial(Expediente $exp): array
    {
        $intentos = $this->intentos($exp->id_expediente)->map(function ($i) {
            $situacion = $i->estado_inscripcion === 'retirada' ? 'retirado' : ($i->condicion ? 'finalizado' : 'en_curso');

            return [
                'id_inscripcion' => $i->id_inscripcion,
                'materia' => ['id_materia' => $i->id_materia, 'codigo' => $i->materia_codigo, 'nombre' => $i->materia_nombre],
                'ciclo' => ['id_ciclo_carrera' => $i->id_ciclo_carrera, 'codigo' => $i->ciclo_codigo, 'nombre' => $i->ciclo_nombre],
                'grupo' => $i->grupo_codigo,
                'situacion' => $situacion,
                'nota_final' => $i->nota_final !== null ? (float) $i->nota_final : null,
                'condicion' => $i->condicion,
                'creditos' => $i->creditos !== null ? (float) $i->creditos : null,
                'version_politica' => $i->version_politica,
                'publicado_en' => $i->publicado_en,
            ];
        });

        return [
            'eventos' => $exp->eventos()->orderBy('id_expediente_evento')->get(['tipo', 'estado_anterior', 'estado_nuevo', 'motivo', 'instante'])
                ->map(fn ($e) => $e->toArray() + ['etiqueta' => ExpedienteEstados::ETIQUETAS[$e->estado_nuevo] ?? null]),
            'en_curso' => $intentos->where('situacion', 'en_curso')->values(),
            'finalizados' => $intentos->where('situacion', 'finalizado')->values(),
            'retirados' => $intentos->where('situacion', 'retirado')->values(),
        ];
    }
}
