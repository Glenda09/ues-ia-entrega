<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\Carrera;
use App\Models\Academico\CicloCarrera;
use App\Models\Academico\ConvocatoriaIngreso;
use App\Models\Academico\Grupo;
use App\Models\Academico\OfertaMateria;
use App\Services\Academico\CalendarioService;
use App\Services\Academico\ConvocatoriaService;
use App\Services\Academico\OfertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CAR-01, CAR-02 y CAR-03: gestión de carrera dentro del alcance asignado.
 */
class GestionCarreraController extends AcademicoController
{
    public function __construct(private CalendarioService $calendario, private OfertaService $oferta, private ConvocatoriaService $convocatorias) {}

    // ---------------- CAR-01 ----------------

    public function ciclos(Request $request, Carrera $carrera): JsonResponse
    {
        $this->actor($request)->exigirCarrera($carrera->id_carrera);

        return $this->paginado($request, CicloCarrera::query()->where('id_carrera', $carrera->id_carrera)->orderBy('id_ciclo_carrera'), fn ($c) => $this->calendario->cicloJson($c));
    }

    public function ciclo(Request $request, Carrera $carrera, CicloCarrera $ciclo): JsonResponse
    {
        $this->actor($request)->exigirCarrera($carrera->id_carrera);
        abort_if((int) $ciclo->id_carrera !== (int) $carrera->id_carrera, 404);

        return $this->ok($this->calendario->cicloJson($ciclo));
    }

    public function crearCiclo(Request $request, Carrera $carrera): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($carrera->id_carrera);
        $d = $this->validarEstricto($request, array_merge([
            'codigo' => 'required|string|min:1|max:50',
            'nombre' => 'required|string|min:1|max:200',
        ], $this->reglasCalendario()));

        return $this->creado($this->calendario->cicloJson($this->calendario->crear($carrera, $d, $actor->idUsuario)), 'Ciclo creado');
    }

    public function actualizarCiclo(Request $request, CicloCarrera $ciclo): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($ciclo->id_carrera);
        $d = $this->validarEstricto($request, array_merge([
            'version' => 'required|integer|min:1',
            'nombre' => 'sometimes|string|min:1|max:200',
            'motivo' => 'nullable|string|max:1000',
        ], $this->reglasCalendario()));

        return $this->ok($this->calendario->cicloJson($this->calendario->actualizar($ciclo, $d, $actor->idUsuario)), 'Ciclo actualizado');
    }

    public function transicionCiclo(Request $request, CicloCarrera $ciclo): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($ciclo->id_carrera);
        $d = $this->validarEstricto($request, ['estado' => 'required|in:publicado,cerrado', 'version' => 'required|integer|min:1']);

        return $this->ok($this->calendario->cicloJson($this->calendario->transicion($ciclo, $d['estado'], (int) $d['version'], $actor->idUsuario)), 'Estado del ciclo actualizado');
    }

    private function reglasCalendario(): array
    {
        $f = 'nullable|date_format:Y-m-d H:i';

        return [
            'calendario' => 'sometimes|array',
            'calendario.inicio' => $f,
            'calendario.fin' => $f,
            'calendario.ventanas' => 'sometimes|array',
            'calendario.ventanas.inscripcion.inicio' => $f,
            'calendario.ventanas.inscripcion.fin_regular' => $f,
            'calendario.ventanas.inscripcion.fin_gracia' => $f,
            'calendario.ventanas.notas.inicio' => $f,
            'calendario.ventanas.notas.fin_regular' => $f,
            'calendario.ventanas.notas.fin_gracia' => $f,
        ];
    }

    // ---------------- CAR-02 ----------------

    public function ofertas(Request $request, CicloCarrera $ciclo): JsonResponse
    {
        $this->actor($request)->exigirCarrera($ciclo->id_carrera);
        $f = $request->validate(['id_materia' => 'nullable|integer', 'id_docente' => 'nullable|integer']);
        $q = OfertaMateria::query()->with('materia')->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)
            ->when($f['id_materia'] ?? null, fn ($q, $m) => $q->where('id_materia', $m))
            ->when($f['id_docente'] ?? null, fn ($q, $d) => $q->whereHas('grupos.asignaciones', fn ($a) => $a->where('id_docente', $d)->whereNull('hasta')))
            ->orderBy('id_oferta_materia');

        return $this->paginado($request, $q, fn ($o) => [
            'id_oferta_materia' => $o->id_oferta_materia,
            'id_materia' => $o->id_materia,
            'materia' => $o->materia->only(['codigo', 'nombre']),
            'grupos' => $o->grupos()->orderBy('id_grupo')->get()->map(fn ($g) => $this->oferta->grupoJson($g))->values(),
        ]);
    }

    public function crearOferta(Request $request, CicloCarrera $ciclo): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($ciclo->id_carrera);
        $d = $this->validarEstricto($request, ['id_materia' => 'required|integer']);
        $o = $this->oferta->crearOferta($ciclo, (int) $d['id_materia'], $actor->idUsuario);

        return $o->wasRecentlyCreated ? $this->creado($o, 'Oferta creada') : $this->ok($o, 'Oferta existente');
    }

    public function crearGrupo(Request $request, OfertaMateria $oferta): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($oferta->ciclo->id_carrera);
        $d = $this->validarEstricto($request, $this->reglasGrupo() + ['codigo' => 'required|string|min:1|max:20']);

        return $this->creado($this->oferta->grupoJson($this->oferta->crearGrupo($oferta, $d, $actor->idUsuario)), 'Grupo creado');
    }

    public function actualizarGrupo(Request $request, Grupo $grupo): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($grupo->oferta->ciclo->id_carrera);
        $d = $this->validarEstricto($request, $this->reglasGrupo() + [
            'version' => 'required|integer|min:1',
            'estado' => 'sometimes|in:borrador,publicado,cancelado',
        ]);

        return $this->ok($this->oferta->grupoJson($this->oferta->actualizarGrupo($grupo, $d, $actor->idUsuario)), 'Grupo actualizado');
    }

    private function reglasGrupo(): array
    {
        return [
            'cupo' => 'sometimes|integer|min:1|max:1000',
            'id_docente' => 'sometimes|nullable|integer',
            'horarios' => 'sometimes|array',
            'horarios.*.dia' => 'required|integer|min:1|max:7',
            'horarios.*.hora_inicio' => 'required|string',
            'horarios.*.hora_fin' => 'required|string',
        ];
    }

    // ---------------- CAR-03 ----------------

    public function convocatorias(Request $request, Carrera $carrera): JsonResponse
    {
        $this->actor($request)->exigirCarrera($carrera->id_carrera);

        return $this->paginado($request, ConvocatoriaIngreso::query()->where('id_carrera', $carrera->id_carrera)->orderBy('id_convocatoria'), fn ($c) => $this->convocatorias->json($c));
    }

    public function crearConvocatoria(Request $request, Carrera $carrera): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($carrera->id_carrera);
        $d = $this->validarEstricto($request, $this->reglasConvocatoria() + [
            'codigo' => 'required|string|min:1|max:50',
            'titulo_publico' => 'required|string|min:1|max:200',
        ]);

        return $this->creado($this->convocatorias->json($this->convocatorias->crear($carrera, $d, $actor->idUsuario)), 'Convocatoria creada');
    }

    public function convocatoria(Request $request, ConvocatoriaIngreso $convocatoria): JsonResponse
    {
        $this->actor($request)->exigirCarrera($convocatoria->id_carrera);

        return $this->ok($this->convocatorias->json($convocatoria));
    }

    public function actualizarConvocatoria(Request $request, ConvocatoriaIngreso $convocatoria): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($convocatoria->id_carrera);
        $d = $this->validarEstricto($request, $this->reglasConvocatoria() + [
            'version' => 'required|integer|min:1',
            'titulo_publico' => 'sometimes|string|min:1|max:200',
            'estado' => 'sometimes|in:borrador,publicada,cerrada',
        ]);

        return $this->ok($this->convocatorias->json($this->convocatorias->actualizar($convocatoria, $d, $actor->idUsuario)), 'Convocatoria actualizada');
    }

    private function reglasConvocatoria(): array
    {
        return [
            'descripcion_publica' => 'nullable|string|max:5000',
            'inicio' => 'nullable|date_format:Y-m-d H:i',
            'fin' => 'nullable|date_format:Y-m-d H:i',
            'requiere_pago' => 'sometimes|boolean',
            'monto_pago' => 'nullable|numeric|min:0',
            'requisitos' => 'sometimes|array',
            'requisitos.*.codigo' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'requisitos.*.tipo' => 'required|in:campo,anexo',
            'requisitos.*.nombre' => 'required|string|max:200',
            'requisitos.*.descripcion' => 'nullable|string|max:2000',
            'requisitos.*.obligatorio' => 'nullable|boolean',
            'requisitos.*.etapa' => 'nullable|in:envio,aceptacion',
            'requisitos.*.condicion' => 'nullable|array',
            'requisitos.*.max_bytes' => 'nullable|integer|min:1024',
            'requisitos.*.tipos_permitidos' => 'nullable|array',
            'requisitos.*.tipos_permitidos.*' => 'in:application/pdf,image/jpeg,image/png',
            'requisitos.*.origen' => 'nullable|string|max:200',
            'ofertas' => 'sometimes|array',
            'ofertas.*.id_sede' => 'required|integer|exists:sede,id_sede',
            'ofertas.*.id_facultad' => 'required|integer|exists:facultad,id_facultad',
            'ofertas.*.modalidad_estudio' => 'required|in:presencial,distancia',
            'ofertas.*.modalidad_ingreso' => 'required|in:general,especial',
        ];
    }
}
