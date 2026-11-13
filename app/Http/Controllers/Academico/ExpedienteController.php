<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\AutoridadDocumental;
use App\Models\Academico\AutoridadDocumentalHistorial;
use App\Models\Academico\DocumentoExpediente;
use App\Models\Academico\Expediente;
use App\Models\Auth\AuthUsuario;
use App\Services\Academico\AuditoriaService;
use App\Services\Academico\DecisionAdmisionService;
use App\Services\Academico\DocumentoService;
use App\Services\Academico\ExpedienteService;
use App\Services\Academico\IdempotenciaService;
use App\Services\Academico\IdentidadAccesoService;
use App\Support\Academico\DominioException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * EXP-03 y EXP-04: expedientes, estados y documentos.
 */
class ExpedienteController extends AcademicoController
{
    public function __construct(private ExpedienteService $expedientes, private DocumentoService $documentos, private IdempotenciaService $idempotencia) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $f = $request->validate(['buscar' => 'nullable|string|max:100', 'estado' => 'nullable|in:ACTIVO,INACTIVO,EGRESADO,RETIRADO', 'id_carrera' => 'nullable|integer']);
        $q = $actor->limitarPorCarrera(Expediente::query())->with('estudiante', 'carrera', 'pensum')
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['id_carrera'] ?? null, fn ($q, $v) => $q->where('id_carrera', $v))
            ->when($f['buscar'] ?? null, fn ($q, $b) => $q->whereHas('estudiante', fn ($e) => $e->whereRaw('LOWER(carnet) LIKE ?', ['%'.mb_strtolower($b).'%'])
                ->orWhereRaw("LOWER(nombres || ' ' || primer_apellido) LIKE ?", ['%'.mb_strtolower($b).'%'])))
            ->orderBy('id_expediente');

        return $this->paginado($request, $q, fn ($e) => $this->expedientes->json($e));
    }

    public function mostrar(Request $request, Expediente $expediente): JsonResponse
    {
        $this->actor($request)->exigirCarrera($expediente->id_carrera);

        return $this->ok($this->expedientes->json($expediente, true));
    }

    public function actualizar(Request $request, Expediente $expediente): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($expediente->id_carrera);
        $prohibidos = array_intersect(array_keys($request->all()), ['carnet', 'email', 'id_carrera', 'id_pensum', 'documento_numero', 'nombres', 'primer_apellido', 'segundo_apellido']);
        if ($prohibidos) {
            throw ValidationException::withMessages(collect($prohibidos)->mapWithKeys(fn ($k) => [$k => ['Este campo requiere un procedimiento especial y no puede editarse aquí.']])->all());
        }
        $d = $this->validarEstricto($request, [
            'version' => 'required|integer|min:1',
            'nombre_uso' => 'sometimes|nullable|string|max:200',
            'telefono' => 'sometimes|nullable|string|max:30',
            'direccion' => 'sometimes|nullable|string|max:300',
            'contacto_emergencia' => 'sometimes|nullable|string|max:300',
        ]);

        return $this->ok($this->expedientes->json($this->expedientes->actualizarDatos($expediente, $d, $actor->idUsuario), true), 'Expediente actualizado');
    }

    public function transicion(Request $request, Expediente $expediente): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($expediente->id_carrera);
        $d = $this->validarEstricto($request, ['estado' => 'required|in:ACTIVO,INACTIVO,EGRESADO,RETIRADO', 'motivo' => 'required|string|min:3|max:1000', 'version' => 'required|integer|min:1']);

        return $this->ok($this->expedientes->json($this->expedientes->transicion($expediente, $d['estado'], (int) $d['version'], $d['motivo'], $actor->idUsuario), true), 'Estado actualizado');
    }

    public function reenviarAcceso(Request $request, Expediente $expediente): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($expediente->id_carrera);
        $cuenta = AuthUsuario::query()->findOrFail($expediente->estudiante->id_usuario);
        $e = app(IdentidadAccesoService::class)->reenviarCredenciales($cuenta);
        app(AuditoriaService::class)->registrar($actor->idUsuario, 'acceso.reenviar', 'expediente', $expediente->id_expediente);

        return $this->ok(['id_entrega' => $e->id_entrega, 'estado' => $e->estado], 'Acceso reenviado');
    }

    public function admisionDirecta(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $d = $this->validarEstricto($request, [
            'nombres' => 'required|string|max:200', 'primer_apellido' => 'required|string|max:100', 'segundo_apellido' => 'nullable|string|max:100',
            'email' => 'required|email|max:255', 'id_carrera' => 'required|integer|exists:carrera,id_carrera', 'fecha_ingreso' => 'required|date_format:Y-m-d',
        ]);
        $actor->exigirCarrera((int) $d['id_carrera']);
        $exp = app(DecisionAdmisionService::class)->admisionDirecta($d, $actor->idUsuario);

        return $this->creado($this->expedientes->json($exp), 'Estudiante admitido');
    }

    // ---------------- EXP-04 ----------------

    public function documentos(Request $request, Expediente $expediente): JsonResponse
    {
        $this->actor($request)->exigirCarrera($expediente->id_carrera);

        return $this->paginado($request, DocumentoExpediente::query()->where('id_expediente', $expediente->id_expediente)->orderByDesc('id_documento'), fn ($d) => $this->documentos->json($d));
    }

    public function documento(Request $request, Expediente $expediente, DocumentoExpediente $documento): JsonResponse
    {
        $this->actor($request)->exigirCarrera($expediente->id_carrera);
        abort_if((int) $documento->id_expediente !== (int) $expediente->id_expediente, 404);

        return $this->ok($this->documentos->json($documento));
    }

    public function emitir(Request $request, Expediente $expediente): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($expediente->id_carrera);
        $d = $this->validarEstricto($request, ['tipo' => 'required|in:certificacion_notas,constancia_estudio']);
        $clave = $this->idempotencia->validarClave($request->header('Idempotency-Key'));
        [$doc, $creado] = $this->documentos->emitir($expediente, $d['tipo'], $clave, $actor->idUsuario);
        $doc->refresh();

        return $creado ? $this->creado($this->documentos->json($doc), 'Documento en emisión') : $this->ok($this->documentos->json($doc), 'Documento existente');
    }

    public function archivo(Request $request, Expediente $expediente, DocumentoExpediente $documento)
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($expediente->id_carrera);
        abort_if((int) $documento->id_expediente !== (int) $expediente->id_expediente, 404);
        if ($documento->estado !== 'emitido' || ! $documento->ruta_archivo) {
            return response()->json(['message' => 'El documento no está disponible.', 'code' => 'DOCUMENTO_NO_DISPONIBLE'], 409);
        }
        app(AuditoriaService::class)->registrar($actor->idUsuario, 'documento.descargar', 'documento_expediente', $documento->id_documento);

        return Storage::disk(DocumentoService::DISCO)->download($documento->ruta_archivo, $documento->folio.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    public function autoridad(): JsonResponse
    {
        $a = AutoridadDocumental::query()->where('clave', 'principal')->where('version', '>', 0)->first();
        $valida = true;
        $error = null;
        try {
            $this->documentos->titularValido();
        } catch (DominioException $e) {
            $valida = false;
            $error = $e->getMessage();
        }

        return $this->ok([
            'titular' => $a ? ['id_usuario' => $a->id_usuario, 'email' => AuthUsuario::find($a->id_usuario)?->email, 'nombre' => $a->nombre_firmante, 'cargo' => $a->cargo, 'version' => (int) $a->version] : null,
            'valida' => $valida,
            'error' => $error,
            'historial' => AutoridadDocumentalHistorial::query()->orderByDesc('id_autoridad_historial')->limit(20)->get(),
        ]);
    }

    public function asignarAutoridad(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, ['id_usuario' => 'required|uuid|exists:auth_usuario,id_usuario', 'nombre' => 'required|string|max:200', 'cargo' => 'required|string|max:200']);
        $a = $this->documentos->asignarAutoridad($d['id_usuario'], $d['nombre'], $d['cargo'], $this->actor($request)->idUsuario);

        return $this->ok(['id_usuario' => $a->id_usuario, 'nombre' => $a->nombre_firmante, 'cargo' => $a->cargo, 'version' => (int) $a->version], 'Autoridad asignada');
    }
}
