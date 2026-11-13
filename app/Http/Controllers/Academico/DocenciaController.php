<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\ActaGrupo;
use App\Models\Academico\AsignacionDocente;
use App\Models\Academico\CicloCarrera;
use App\Models\Academico\Docente;
use App\Models\Academico\Grupo;
use App\Models\Academico\Inscripcion;
use App\Services\Academico\CalendarioService;
use App\Services\Academico\CalificacionService;
use App\Services\Academico\OfertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DOC-01, DOC-02 y GEN-04.
 */
class DocenciaController extends AcademicoController
{
    public function __construct(private CalificacionService $calificaciones, private OfertaService $oferta, private CalendarioService $calendario) {}

    public function misGrupos(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $docente = Docente::query()->where('id_usuario', $actor->idUsuario)->where('estado', 'activo')->first();
        $ids = $docente ? AsignacionDocente::query()->where('id_docente', $docente->id_docente)->whereNull('hasta')->pluck('id_grupo') : collect();
        $q = Grupo::query()->whereIn('id_grupo', $ids)->with('oferta.ciclo.carrera', 'oferta.materia')->orderBy('id_grupo');

        return $this->paginado($request, $q, fn ($g) => $this->grupoDocenteJson($g));
    }

    public function miGrupo(Request $request, int $id_grupo): JsonResponse
    {
        return $this->ok($this->grupoDocenteJson($this->calificaciones->grupoAsignado($this->actor($request), $id_grupo)));
    }

    public function padron(Request $request, int $id_grupo): JsonResponse
    {
        $g = $this->calificaciones->grupoAsignado($this->actor($request), $id_grupo);
        $q = Inscripcion::query()->where('id_grupo', $g->id_grupo)->with('expediente.estudiante')->orderBy('id_inscripcion');

        return $this->paginado($request, $q, fn ($i) => [
            'id_inscripcion' => $i->id_inscripcion,
            'carnet' => $i->expediente->estudiante->carnet,
            'nombre' => trim(($i->expediente->estudiante->nombre_uso ?: $i->expediente->estudiante->nombres).' '.$i->expediente->estudiante->primer_apellido.' '.($i->expediente->estudiante->segundo_apellido ?? '')),
            'estado' => $i->estado,
        ]);
    }

    public function evaluaciones(Request $request, int $id_grupo): JsonResponse
    {
        return $this->ok($this->calificaciones->cuadricula($this->calificaciones->grupoAsignado($this->actor($request), $id_grupo)));
    }

    public function guardarEvaluaciones(Request $request, int $id_grupo): JsonResponse
    {
        $actor = $this->actor($request);
        $g = $this->calificaciones->grupoAsignado($actor, $id_grupo);
        $d = $this->validarEstricto($request, ['evaluaciones' => 'required|array|min:1|max:20', 'evaluaciones.*.nombre' => 'required|string|max:100', 'evaluaciones.*.ponderacion' => 'required|numeric']);

        return $this->ok($this->calificaciones->guardarEstructura($g, $d['evaluaciones'], $actor->idUsuario), 'Estructura guardada');
    }

    public function guardarNotas(Request $request, int $id_grupo): JsonResponse
    {
        $actor = $this->actor($request);
        $g = $this->calificaciones->grupoAsignado($actor, $id_grupo);
        $d = $this->validarEstricto($request, [
            'notas' => 'required|array|min:1',
            'notas.*.id_evaluacion' => 'required|integer',
            'notas.*.id_inscripcion' => 'required|integer',
            'notas.*.valor' => 'nullable|numeric',
            'notas.*.condicion' => 'nullable|in:nota,ausente',
            'notas.*.version' => 'required|integer|min:0',
        ]);

        return $this->ok($this->calificaciones->guardarNotas($actor, $g, $d['notas']), 'Notas guardadas');
    }

    public function calculoFinal(Request $request, int $id_grupo): JsonResponse
    {
        $actor = $this->actor($request);
        $g = $this->calificaciones->grupoAsignado($actor, $id_grupo);

        return $this->ok($this->calificaciones->prepararCalculo($g, $actor->idUsuario), 'Cálculo preparado');
    }

    private function grupoDocenteJson(Grupo $g): array
    {
        $ciclo = $g->oferta->ciclo;
        $acta = ActaGrupo::query()->where('id_grupo', $g->id_grupo)->first();

        return $this->oferta->grupoJson($g) + [
            'carrera' => $ciclo->carrera->only(['id_carrera', 'codigo', 'nombre']),
            'ciclo' => $ciclo->only(['id_ciclo_carrera', 'codigo', 'nombre', 'estado']),
            'materia' => $g->oferta->materia->only(['id_materia', 'codigo', 'nombre']),
            'ventana_notas' => $this->calendario->estadoVentana($ciclo, 'notas'),
            'acta' => $acta ? ['estado' => $acta->estado, 'version' => (int) $acta->version] : null,
        ];
    }

    // ---------------- Actas (gestor) ----------------

    public function actas(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $f = $request->validate(['id_ciclo' => 'nullable|integer', 'estado' => 'nullable|in:pendiente,calculada,publicada']);
        $q = Grupo::query()->where('grupo.estado', 'publicado')
            ->whereHas('oferta.ciclo', fn ($c) => $actor->limitarPorCarrera($c)->when($f['id_ciclo'] ?? null, fn ($q, $v) => $q->where('id_ciclo_carrera', $v)))
            ->with('oferta.ciclo.carrera', 'oferta.materia', 'acta')
            ->when(($f['estado'] ?? null) === 'publicada', fn ($q) => $q->whereHas('acta', fn ($a) => $a->where('estado', 'publicada')))
            ->when(($f['estado'] ?? null) === 'calculada', fn ($q) => $q->whereHas('acta', fn ($a) => $a->where('estado', 'borrador')->whereNotNull('calculado_en')))
            ->when(($f['estado'] ?? null) === 'pendiente', fn ($q) => $q->where(fn ($w) => $w->whereDoesntHave('acta')->orWhereHas('acta', fn ($a) => $a->where('estado', 'borrador')->whereNull('calculado_en'))))
            ->orderBy('id_grupo');

        return $this->paginado($request, $q, fn ($g) => [
            'id_grupo' => $g->id_grupo, 'codigo' => $g->codigo,
            'materia' => $g->oferta->materia->only(['codigo', 'nombre']),
            'ciclo' => $g->oferta->ciclo->only(['id_ciclo_carrera', 'codigo', 'nombre']),
            'carrera' => $g->oferta->ciclo->carrera->only(['id_carrera', 'nombre']),
            'acta' => $g->acta ? ['estado' => $g->acta->estado, 'version' => (int) $g->acta->version, 'calculado_en' => $g->acta->calculado_en?->toIso8601String(), 'publicado_en' => $g->acta->publicado_en?->toIso8601String()] : null,
            'situacion' => ! $g->acta ? 'pendiente' : ($g->acta->estado === 'publicada' ? 'publicada' : ($g->acta->calculado_en ? 'calculada' : 'pendiente')),
            'inscritos' => Inscripcion::query()->where('id_grupo', $g->id_grupo)->where('estado', 'activa')->count(),
        ]);
    }

    public function acta(Request $request, Grupo $grupo): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($grupo->oferta->ciclo->id_carrera);
        $acta = ActaGrupo::query()->where('id_grupo', $grupo->id_grupo)->first();
        $calculo = $acta ? $this->calificaciones->calcular($grupo, $acta) : null;
        $publicados = $acta && $acta->estado === 'publicada' ? $acta->resultados()->with('acta')->get()->map->only(['id_inscripcion', 'nota_final', 'condicion', 'creditos', 'version_politica', 'detalle']) : null;

        return $this->ok([
            'grupo' => $this->grupoDocenteJson($grupo),
            'cuadricula' => $this->calificaciones->cuadricula($grupo),
            'calculo' => $calculo,
            'resultados_publicados' => $publicados,
        ]);
    }

    public function publicar(Request $request, Grupo $grupo): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($grupo->oferta->ciclo->id_carrera);
        $d = $this->validarEstricto($request, ['version' => 'required|integer|min:1']);
        $acta = $this->calificaciones->publicar($actor, $grupo, (int) $d['version']);

        return $this->ok(['estado' => $acta->estado, 'version' => (int) $acta->version, 'publicado_en' => $acta->publicado_en?->toIso8601String()], 'Acta publicada');
    }

    // ---------------- GEN-04 ----------------

    public function ciclosInstitucionales(Request $request): JsonResponse
    {
        $f = $request->validate(['id_carrera' => 'nullable|integer', 'codigo' => 'nullable|string|max:50', 'estado' => 'nullable|in:borrador,publicado,cerrado', 'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d']);
        $q = CicloCarrera::query()->with('carrera')
            ->when($f['id_carrera'] ?? null, fn ($q, $v) => $q->where('id_carrera', $v))
            ->when($f['codigo'] ?? null, fn ($q, $v) => $q->whereRaw('LOWER(codigo) LIKE ?', ['%'.mb_strtolower($v).'%']))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->where('fin', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->where('inicio', '<=', $v))
            ->orderBy('id_ciclo_carrera');

        return $this->paginado($request, $q, fn ($c) => $this->resumenCiclo($c));
    }

    public function cicloInstitucional(CicloCarrera $ciclo): JsonResponse
    {
        $grupos = Grupo::query()->whereHas('oferta', fn ($q) => $q->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera))
            ->with('oferta.materia')->orderBy('id_grupo')->get()
            ->map(fn ($g) => [
                'id_grupo' => $g->id_grupo, 'codigo' => $g->codigo, 'estado' => $g->estado, 'cupo' => (int) $g->cupo,
                'materia' => $g->oferta->materia->only(['codigo', 'nombre']),
                'inscripciones_activas' => Inscripcion::query()->where('id_grupo', $g->id_grupo)->where('estado', 'activa')->count(),
            ]);

        return $this->ok($this->resumenCiclo($ciclo) + ['grupos' => $grupos]);
    }

    private function resumenCiclo(CicloCarrera $c): array
    {
        $agg = DB::table('inscripcion')->where('id_ciclo_carrera', $c->id_ciclo_carrera)->where('estado', 'activa')
            ->selectRaw('COUNT(*) as inscripciones, COUNT(DISTINCT id_expediente) as estudiantes')->first();

        return [
            'id_ciclo_carrera' => $c->id_ciclo_carrera,
            'carrera' => $c->carrera->only(['id_carrera', 'codigo', 'nombre']),
            'codigo' => $c->codigo, 'nombre' => $c->nombre, 'estado' => $c->estado,
            'calendario' => $this->calendario->calendarioJson($c),
            'ventanas_estado' => ['inscripcion' => $this->calendario->estadoVentana($c, 'inscripcion')['estado'], 'notas' => $this->calendario->estadoVentana($c, 'notas')['estado']],
            'grupos_publicados' => Grupo::query()->where('estado', 'publicado')->whereHas('oferta', fn ($q) => $q->where('id_ciclo_carrera', $c->id_ciclo_carrera))->count(),
            'inscripciones_activas' => (int) $agg->inscripciones,
            'estudiantes_distintos' => (int) $agg->estudiantes,
        ];
    }
}
