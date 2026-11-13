<?php

namespace App\Http\Controllers\Academico;

use App\Models\Academico\ArchivoIngreso;
use App\Models\Academico\ConvocatoriaIngreso;
use App\Models\Academico\DecisionIngreso;
use App\Models\Academico\OrdenPagoEvento;
use App\Models\Academico\OrdenPagoIngreso;
use App\Models\Academico\SolicitudAnexo;
use App\Models\Academico\SolicitudIngreso;
use App\Models\Academico\SolicitudIngresoRevision;
use App\Services\Academico\AdmisionService;
use App\Services\Academico\AuditoriaService;
use App\Services\Academico\ConvocatoriaService;
use App\Services\Academico\DecisionAdmisionService;
use App\Services\Academico\IdempotenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * EXP-01 (aspirante y público) y EXP-02 (gestor de expediente).
 */
class AdmisionController extends AcademicoController
{
    public function __construct(
        private AdmisionService $admision,
        private DecisionAdmisionService $decisiones,
        private ConvocatoriaService $convocatorias,
        private IdempotenciaService $idempotencia,
    ) {}

    // ---------------- Público ----------------

    public function verificacion(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, ['email' => 'required|email:rfc|max:255']);
        $this->admision->solicitarVerificacion($d['email'], (string) $request->ip());

        return response()->json(['message' => 'Si el correo puede registrarse, recibirás un código de verificación. Si ya tienes cuenta, inicia sesión o recupera tu contraseña.']);
    }

    public function registro(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'email' => 'required|email:rfc|max:255',
            'codigo_verificacion' => 'required|digits:6',
            'password' => 'required|string|min:8|max:100|same:password_confirmation',
            'password_confirmation' => 'required|string',
        ]);
        $this->admision->registrar($d['email'], $d['codigo_verificacion'], $d['password']);

        return response()->json(['message' => 'Registro procesado. Inicia sesión con tu correo y contraseña. Si ya tenías cuenta, usa tu contraseña actual o recupérala.'], 201);
    }

    public function correccion(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|max:100',
            'datos' => 'sometimes|array',
            'archivos' => 'sometimes|array',
            'archivos.*' => 'file',
        ]);
        $sol = $this->admision->corregir((string) $request->input('token'), (array) $request->input('datos', []), (array) $request->file('archivos', []));

        return $this->ok(['folio' => $sol->folio, 'estado' => $sol->estado, 'revision' => $sol->revision_actual], 'Corrección enviada');
    }

    // ---------------- Aspirante ----------------

    public function vincularPerfil(Request $request): JsonResponse
    {
        $this->admision->vincularAspirante($this->actor($request)->idUsuario);

        return $this->ok(null, 'Perfil de aspirante vinculado');
    }

    public function miBorrador(Request $request): JsonResponse
    {
        $f = $request->validate(['convocatoria' => 'required|integer']);
        $actor = $this->actor($request);
        $conv = ConvocatoriaIngreso::query()->where('estado', '!=', 'borrador')->findOrFail($f['convocatoria']);
        $b = $this->admision->borrador($actor->idUsuario, $conv->id_convocatoria);

        return $this->ok($this->borradorJson($actor->idUsuario, $conv, $b));
    }

    public function guardarBorrador(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'convocatoria' => 'required|integer',
            'version' => 'required|integer|min:0',
            'etapa' => 'required|integer|min:1|max:5',
            'datos' => 'present|array',
        ]);
        $actor = $this->actor($request);
        $b = $this->admision->guardarBorrador($actor->idUsuario, (int) $d['convocatoria'], (int) $d['version'], (int) $d['etapa'], $d['datos']);

        return $this->ok($this->borradorJson($actor->idUsuario, ConvocatoriaIngreso::find($b->id_convocatoria), $b), 'Borrador guardado');
    }

    public function adjuntar(Request $request): JsonResponse
    {
        $d = $this->validarEstricto($request, [
            'convocatoria' => 'required|integer',
            'codigo_requisito' => 'required|string|max:50',
            'version' => 'required|integer|min:1',
            'archivo' => 'required|file',
        ]);
        $actor = $this->actor($request);
        $b = $this->admision->adjuntar($actor->idUsuario, (int) $d['convocatoria'], $d['codigo_requisito'], $request->file('archivo'), (int) $d['version']);

        return $this->ok($this->borradorJson($actor->idUsuario, ConvocatoriaIngreso::find($b->id_convocatoria), $b), 'Documento adjuntado');
    }

    public function enviar(Request $request, ConvocatoriaIngreso $convocatoria): JsonResponse
    {
        $d = $this->validarEstricto($request, ['version_requisitos' => 'required|integer|min:1']);
        $actor = $this->actor($request);
        $clave = $this->idempotencia->validarClave($request->header('Idempotency-Key'));
        [$estado, $cuerpo] = $this->idempotencia->ejecutar('usuario:'.$actor->idUsuario, 'solicitud.enviar', 'convocatoria:'.$convocatoria->id_convocatoria, $clave, $d,
            function () use ($actor, $convocatoria, $d) {
                [$estado, $data] = $this->admision->enviar($actor->idUsuario, $convocatoria->id_convocatoria, (int) $d['version_requisitos']);

                return [$estado, ['message' => 'Solicitud recibida', 'data' => $data]];
            });

        return response()->json($cuerpo, $estado);
    }

    public function misSolicitudes(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        return $this->paginado($request, SolicitudIngreso::query()->where('id_usuario', $actor->idUsuario)->orderBy('id_solicitud'), fn ($s) => $this->admision->solicitudPropiaJson($s));
    }

    public function miSolicitud(Request $request, SolicitudIngreso $solicitud): JsonResponse
    {
        abort_unless($solicitud->id_usuario === $this->actor($request)->idUsuario, 404);

        return $this->ok($this->admision->solicitudPropiaJson($solicitud));
    }

    private function borradorJson(string $idUsuario, ConvocatoriaIngreso $conv, $b): array
    {
        $orden = OrdenPagoIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $conv->id_convocatoria)->first();
        $aplicables = [];
        if ($b) {
            [$req] = $this->admision->evaluarRequisitos($conv, $b->datos ?? [], (int) $conv->version_requisitos);
            $aplicables = $req->pluck('codigo')->values();
        }
        $solicitud = SolicitudIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $conv->id_convocatoria)->first();

        return [
            'convocatoria' => $this->convocatorias->json($conv, true),
            'borrador' => $b ? [
                'etapa' => (int) $b->etapa,
                'datos' => $b->datos ?? [],
                'anexos' => collect($b->anexos ?? [])->map(fn ($id) => ArchivoIngreso::query()->whereKey($id)->first(['id_archivo', 'nombre_seguro', 'mime', 'tamano']))->filter(),
                'version' => (int) $b->version,
            ] : null,
            'requisitos_aplicables' => $aplicables,
            'orden_pago' => $orden ? ['folio' => $orden->folio, 'monto' => $orden->monto, 'estado' => $orden->estado, 'observacion' => $orden->motivo_observacion] : null,
            'solicitud' => $solicitud ? $this->admision->solicitudPropiaJson($solicitud) : null,
        ];
    }

    // ---------------- Gestor (EXP-02) ----------------

    public function solicitudes(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $f = $request->validate(['id_carrera' => 'nullable|integer', 'id_convocatoria' => 'nullable|integer', 'estado' => 'nullable|in:recibida,observada,aceptada']);
        $q = $actor->limitarPorCarrera(SolicitudIngreso::query())
            ->when($f['id_carrera'] ?? null, fn ($q, $v) => $q->where('id_carrera', $v))
            ->when($f['id_convocatoria'] ?? null, fn ($q, $v) => $q->where('id_convocatoria', $v))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->with('carrera:id_carrera,codigo,nombre')
            ->orderBy('id_solicitud');

        return $this->paginado($request, $q, fn ($s) => [
            'id_solicitud' => $s->id_solicitud, 'folio' => $s->folio, 'estado' => $s->estado, 'id_carrera' => $s->id_carrera,
            'carrera' => $s->carrera?->nombre, 'id_convocatoria' => $s->id_convocatoria,
            'nombre' => trim($s->nombres.' '.$s->primer_apellido.' '.($s->segundo_apellido ?? '')),
            'revision_actual' => $s->revision_actual, 'creado_en' => $s->created_at?->toIso8601String(),
        ]);
    }

    public function solicitud(Request $request, SolicitudIngreso $solicitud): JsonResponse
    {
        $this->actor($request)->exigirCarrera($solicitud->id_carrera);
        $revisiones = SolicitudIngresoRevision::query()->where('id_solicitud', $solicitud->id_solicitud)->orderBy('revision')->get();
        $orden = OrdenPagoIngreso::query()->where('id_usuario', $solicitud->id_usuario)->where('id_convocatoria', $solicitud->id_convocatoria)->first();

        return $this->ok([
            'id_solicitud' => $solicitud->id_solicitud,
            'folio' => $solicitud->folio,
            'estado' => $solicitud->estado,
            'version' => (int) $solicitud->version,
            'version_requisitos' => (int) $solicitud->version_requisitos,
            'revision_actual' => $solicitud->revision_actual,
            'id_carrera' => $solicitud->id_carrera,
            'carrera' => $solicitud->carrera?->nombre,
            'convocatoria' => $solicitud->convocatoria?->titulo_publico,
            'identidad' => $solicitud->only(['nombres', 'primer_apellido', 'segundo_apellido', 'email', 'nacionalidad', 'documento_tipo', 'documento_numero']) + ['fecha_nacimiento' => $solicitud->fecha_nacimiento?->toDateString()],
            'motivo_observacion' => $solicitud->motivo_observacion,
            'revisiones' => $revisiones->map(fn ($r) => [
                'revision' => $r->revision, 'instante' => $r->instante?->toIso8601String(), 'edad' => $r->edad, 'datos' => $r->datos,
                'anexos' => SolicitudAnexo::query()->where('id_revision', $r->id_revision)->with('archivo:id_archivo,nombre_seguro,mime,tamano')->get()
                    ->map(fn ($a) => ['id_anexo' => $a->id_anexo, 'codigo_requisito' => $a->codigo_requisito, 'nombre' => $a->archivo?->nombre_seguro, 'mime' => $a->archivo?->mime, 'tamano' => $a->archivo?->tamano]),
            ]),
            'decisiones' => DecisionIngreso::query()->where('id_solicitud', $solicitud->id_solicitud)->orderBy('id_decision')->get(['revision', 'estado', 'motivo', 'instante', 'id_expediente']),
            'pago' => $orden ? [
                'folio' => $orden->folio, 'monto' => $orden->monto, 'estado' => $orden->estado, 'version' => (int) $orden->version,
                'referencia' => $orden->referencia_comprobante, 'observacion' => $orden->motivo_observacion,
                'eventos' => OrdenPagoEvento::query()->where('id_orden_pago', $orden->id_orden_pago)->orderBy('id_orden_pago_evento')->get(['estado_anterior', 'estado_nuevo', 'motivo', 'instante']),
            ] : null,
        ]);
    }

    public function anexo(Request $request, SolicitudIngreso $solicitud, int $anexo): StreamedResponse
    {
        $this->actor($request)->exigirCarrera($solicitud->id_carrera);
        $a = SolicitudAnexo::query()->whereKey($anexo)
            ->whereIn('id_revision', SolicitudIngresoRevision::query()->where('id_solicitud', $solicitud->id_solicitud)->select('id_revision'))
            ->with('archivo')->firstOrFail();
        app(AuditoriaService::class)->registrar($this->actor($request)->idUsuario, 'solicitud.anexo.descargar', 'solicitud_anexo', $a->id_anexo);

        return Storage::disk($a->archivo->disco)->download($a->archivo->ruta, $a->archivo->nombre_seguro, ['Content-Type' => $a->archivo->mime, 'Cache-Control' => 'private, no-store']);
    }

    public function decidir(Request $request, SolicitudIngreso $solicitud): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($solicitud->id_carrera);
        $d = $this->validarEstricto($request, [
            'estado' => 'required|in:observada,aceptada',
            'version' => 'required|integer|min:1',
            'motivo' => 'required_if:estado,observada|nullable|string|min:3|max:1000',
            'fecha_ingreso' => 'required_if:estado,aceptada|nullable|date_format:Y-m-d',
        ]);
        if ($d['estado'] === 'observada') {
            $s = $this->decisiones->observar($solicitud, (int) $d['version'], $d['motivo'], $actor->idUsuario);

            return $this->ok(['estado' => $s->estado, 'version' => (int) $s->version], 'Solicitud observada');
        }
        $exp = $this->decisiones->aceptar($solicitud, (int) $d['version'], $d['fecha_ingreso'], $actor->idUsuario);

        return $this->ok(['estado' => 'aceptada', 'id_expediente' => $exp->id_expediente, 'carnet' => $exp->estudiante->carnet], 'Solicitud aceptada');
    }

    public function revisarPago(Request $request, SolicitudIngreso $solicitud): JsonResponse
    {
        $actor = $this->actor($request);
        $actor->exigirCarrera($solicitud->id_carrera);
        $d = $this->validarEstricto($request, ['estado' => 'required|in:validado,observado', 'version' => 'required|integer|min:1', 'motivo' => 'nullable|string|max:1000']);
        $o = $this->admision->revisarPago($solicitud, $d['estado'], (int) $d['version'], $d['motivo'] ?? null, $actor->idUsuario);

        return $this->ok(['estado' => $o->estado, 'version' => (int) $o->version], 'Pago revisado');
    }
}
