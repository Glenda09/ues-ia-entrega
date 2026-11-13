<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\Estudiante;
use App\Models\Academico\Inscripcion;
use App\Services\Academico\AvanceService;
use App\Services\Academico\ElegibilidadService;
use App\Services\Academico\ExpedienteEstados;
use App\Services\Academico\IdempotenciaService;
use App\Services\Academico\InscripcionService;
use App\Support\Academico\DominioException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * EST-01, EST-02, INS-01 e INS-02: portal propio del estudiante.
 */
class EstudianteController extends AcademicoController
{
    public function __construct(
        private ElegibilidadService $elegibilidad,
        private InscripcionService $inscripciones,
        private AvanceService $avance,
        private IdempotenciaService $idempotencia,
    ) {}

    public function miPerfil(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $est = Estudiante::query()->where('id_usuario', $actor->idUsuario)->first();
        if (! $est) {
            throw DominioException::noEncontrado('No tienes un registro de estudiante.');
        }

        return $this->ok([
            'carnet' => $est->carnet,
            'nombre' => trim(($est->nombre_uso ?: $est->nombres).' '.$est->primer_apellido.' '.($est->segundo_apellido ?? '')),
            'email' => $est->email,
            'expedientes' => $est->expedientes()->with('carrera', 'pensum')->orderBy('id_expediente')->get()->map(fn ($e) => [
                'id_expediente' => $e->id_expediente, 'id_carrera' => $e->id_carrera, 'carrera' => $e->carrera->nombre, 'codigo_carrera' => $e->carrera->codigo,
                'pensum' => $e->pensum->version_curricular, 'estado' => $e->estado, 'estado_etiqueta' => ExpedienteEstados::ETIQUETAS[$e->estado], 'fecha_ingreso' => $e->fecha_ingreso?->toDateString(),
            ]),
        ]);
    }

    public function miCalendario(Request $request): JsonResponse
    {
        $f = $request->validate(['expediente' => 'required|integer']);
        $exp = $this->elegibilidad->expedientePropio($this->actor($request), (int) $f['expediente']);

        return $this->ok($this->avance->calendario($exp));
    }

    public function miAvance(Request $request): JsonResponse
    {
        $f = $request->validate(['expediente' => 'required|integer']);
        $exp = $this->elegibilidad->expedientePropio($this->actor($request), (int) $f['expediente']);

        return $this->ok($this->avance->avance($exp));
    }

    public function miOferta(Request $request): JsonResponse
    {
        $f = $request->validate(['expediente' => 'required|integer', 'ciclo' => 'required|integer', 'grupos' => 'nullable|array', 'grupos.*' => 'integer']);
        $actor = $this->actor($request);
        $exp = $this->elegibilidad->expedientePropio($actor, (int) $f['expediente']);
        $ciclo = $this->elegibilidad->cicloDe($exp, (int) $f['ciclo']);

        return $this->ok($this->elegibilidad->evaluar($actor, $exp, $ciclo, array_map('intval', $f['grupos'] ?? [])));
    }

    public function misInscripciones(Request $request): JsonResponse
    {
        $f = $request->validate(['expediente' => 'required|integer', 'ciclo' => 'nullable|integer']);
        $actor = $this->actor($request);
        $exp = $this->elegibilidad->expedientePropio($actor, (int) $f['expediente']);
        $q = Inscripcion::query()->where('id_expediente', $exp->id_expediente)
            ->when($f['ciclo'] ?? null, fn ($q, $c) => $q->where('id_ciclo_carrera', $c))
            ->with('materia', 'grupo')->orderBy('id_inscripcion');

        return $this->paginado($request, $q, fn ($i) => $this->inscripciones->json($i));
    }

    public function confirmar(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'id_expediente' => 'required|integer',
            'id_ciclo' => 'required|integer',
            'grupos' => 'required|array|min:1',
            'grupos.*' => 'integer|distinct',
        ]);
        $actor = $this->actor($request);
        $exp = $this->elegibilidad->expedientePropio($actor, (int) $d['id_expediente']);
        $ciclo = $this->elegibilidad->cicloDe($exp, (int) $d['id_ciclo']);
        $clave = $this->idempotencia->validarClave($request->header('Idempotency-Key'));
        [$estado, $cuerpo] = $this->idempotencia->ejecutar('usuario:'.$actor->idUsuario, 'inscripcion.confirmar', 'expediente:'.$exp->id_expediente, $clave, $d,
            fn () => [201, ['message' => 'Inscripción confirmada', 'data' => collect($this->inscripciones->confirmar($actor, $exp, $ciclo, $d['grupos']))->map(fn ($i) => $this->inscripciones->json($i))->values()->all()]]);

        return response()->json($cuerpo, $estado);
    }

    public function retirar(Request $request, Inscripcion $inscripcion): JsonResponse
    {
        $d = $this->validarEstricto($request, ['motivo' => 'required|string|min:3|max:1000']);
        $actor = $this->actor($request);
        $this->elegibilidad->expedientePropio($actor, (int) $inscripcion->id_expediente);

        return $this->ok($this->inscripciones->json($this->inscripciones->retirar($actor, $inscripcion, $d['motivo'])), 'Inscripción retirada');
    }
}
