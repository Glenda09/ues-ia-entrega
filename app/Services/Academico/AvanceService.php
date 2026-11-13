<?php

namespace App\Services\Academico;

use App\Models\Academico\CicloCarrera;
use App\Models\Academico\Expediente;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\PensumEquivalencia;
use App\Models\Academico\PensumMateria;
use App\Models\Academico\PensumRequisito;

/**
 * EST-02: avance de carrera por expediente con procedencia de cada cálculo.
 */
class AvanceService
{
    public function __construct(private HistorialAcademicoService $historial, private ConfiguracionService $configuracion, private CalendarioService $calendario) {}

    /**
     * @return array<string, mixed>
     */
    public function avance(Expediente $exp): array
    {
        $materias = PensumMateria::query()->where('id_pensum', $exp->id_pensum)->with('materia')->orderBy('nivel')->orderBy('orden')->get();
        $requisitos = PensumRequisito::query()->where('id_pensum', $exp->id_pensum)->get();
        $intentos = $this->historial->intentos($exp->id_expediente);
        $equivalencias = $this->configuracion->valor('inscripcion.equivalencias_activas') ? PensumEquivalencia::query()->where('id_pensum', $exp->id_pensum)->get() : collect();
        $aprobadasOrigen = $intentos->where('condicion', 'aprobada')->pluck('id_materia')->map(fn ($i) => (int) $i)->unique()->all();
        $porEquivalencia = [];
        foreach ($equivalencias as $e) {
            if (in_array((int) $e->id_materia_origen, $aprobadasOrigen, true)) {
                $porEquivalencia[(int) $e->id_materia_destino] = (int) $e->id_materia_origen;
            }
        }

        $filas = $materias->map(function ($pm) use ($intentos, $requisitos, $porEquivalencia, $materias) {
            $propios = $intentos->where('id_materia', $pm->id_materia)->values();
            $aprobado = $propios->where('condicion', 'aprobada')->last();
            $enCurso = $propios->where('estado_inscripcion', 'activa')->whereNull('condicion')->last();
            $ultimoReprobado = $propios->where('condicion', 'reprobada')->last();
            [$condicion, $sustento] = match (true) {
                (bool) $aprobado => ['aprobada', $aprobado],
                isset($porEquivalencia[$pm->id_materia]) => ['aprobada', null],
                (bool) $enCurso => ['en_curso', $enCurso],
                (bool) $ultimoReprobado => ['reprobada', $ultimoReprobado],
                default => ['pendiente', null],
            };

            return [
                'id_materia' => $pm->id_materia,
                'codigo' => $pm->materia->codigo,
                'nombre' => $pm->materia->nombre,
                'nivel' => $pm->nivel,
                'creditos' => (float) $pm->creditos,
                'obligatoria' => (bool) $pm->obligatoria,
                'condicion' => $condicion,
                'acreditada_por_equivalencia' => isset($porEquivalencia[$pm->id_materia]) && ! $aprobado,
                'intento_sustento' => $sustento ? ['id_inscripcion' => $sustento->id_inscripcion, 'ciclo' => $sustento->ciclo_codigo, 'nota_final' => $sustento->nota_final !== null ? (float) $sustento->nota_final : null] : null,
                'requisitos' => $requisitos->where('id_materia', $pm->id_materia)->map(fn ($r) => ['id_materia' => $r->id_materia_requisito, 'codigo' => $materias->firstWhere('id_materia', $r->id_materia_requisito)?->materia->codigo, 'tipo' => $r->tipo])->values(),
                'intentos' => $propios->map(fn ($i) => ['id_inscripcion' => $i->id_inscripcion, 'ciclo' => $i->ciclo_codigo, 'estado' => $i->estado_inscripcion, 'condicion' => $i->condicion, 'nota_final' => $i->nota_final !== null ? (float) $i->nota_final : null])->values(),
            ];
        });

        $obligatorias = $filas->where('obligatoria', true);
        $requeridos = round($obligatorias->sum('creditos'), 2);
        $aprobados = round($obligatorias->where('condicion', 'aprobada')->sum('creditos'), 2);
        $porcentaje = $requeridos > 0 ? round(100 * $aprobados / $requeridos + 1e-9, 2) : null;

        return [
            'id_expediente' => $exp->id_expediente,
            'pensum' => ['id_pensum' => $exp->id_pensum, 'version_curricular' => $exp->pensum?->version_curricular],
            'resumen' => [
                'creditos_requeridos' => $requeridos,
                'creditos_aprobados' => $aprobados,
                'creditos_pendientes' => round($requeridos - $aprobados, 2),
                'porcentaje' => $porcentaje,
                'aviso' => $requeridos > 0 ? null : 'El pensum no tiene créditos obligatorios.',
                'optativas_aprobadas' => round($filas->where('obligatoria', false)->where('condicion', 'aprobada')->sum('creditos'), 2),
                'regla' => 'Requeridos = créditos obligatorios; aprobados cuentan una vez por materia con resultados publicados (y equivalencias si están activas).',
            ],
            'niveles' => $filas->groupBy('nivel')->map(fn ($f, $n) => ['nivel' => (int) $n, 'materias' => $f->values()])->values(),
        ];
    }

    /**
     * Calendario del expediente: ciclos publicados de su carrera con ventanas vigentes y próximas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calendario(Expediente $exp): array
    {
        return CicloCarrera::query()->where('id_carrera', $exp->id_carrera)->whereIn('estado', ['publicado', 'cerrado'])->orderByDesc('inicio')->get()
            ->map(fn ($c) => [
                'id_ciclo' => $c->id_ciclo_carrera, 'codigo' => $c->codigo, 'nombre' => $c->nombre, 'estado' => $c->estado,
                'calendario' => $this->calendario->calendarioJson($c),
                'inscripcion' => $this->calendario->estadoVentana($c, 'inscripcion'),
                'notas' => $this->calendario->estadoVentana($c, 'notas'),
                'inscripciones_activas' => Inscripcion::query()->where('id_expediente', $exp->id_expediente)->where('id_ciclo_carrera', $c->id_ciclo_carrera)->where('estado', 'activa')->count(),
            ])->values()->all();
    }
}
