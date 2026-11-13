<?php

namespace App\Services\Academico;

use App\Jobs\Academico\GenerarDocumentoJob;
use App\Models\Academico\AutoridadDocumental;
use App\Models\Academico\AutoridadDocumentalHistorial;
use App\Models\Academico\DocumentoExpediente;
use App\Models\Academico\Expediente;
use App\Models\Academico\Inscripcion;
use App\Models\App\AppPersonalizacion;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use App\Services\App\PersonalizacionService;
use App\Services\Auth\PermisosEfectivosService;
use App\Support\Academico\DominioException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EXP-04 (D07): autoridad documental exclusiva y emisión de certificación de notas y constancia de estudio.
 */
class DocumentoService
{
    public const PERFIL_AUTORIDAD = 'academico_autoridad_documental';

    public const PERMISO_AUTORIDAD = 'academico_documento_autoridad';

    public const VERSION_PLANTILLA = 'v1';

    public const DISCO = 'local';

    public function __construct(
        private HistorialAcademicoService $historial,
        private CalendarioService $calendario,
        private AuditoriaService $auditoria,
        private PermisosEfectivosService $permisos,
        private PersonalizacionService $personalizacion,
    ) {}

    // ---------------- Autoridad ----------------

    /**
     * Asigna o reemplaza al titular: bloquea el singleton y sincroniza el perfil dedicado en la misma transacción.
     */
    public function asignarAutoridad(string $idUsuario, string $nombre, string $cargo, string $actor): AutoridadDocumental
    {
        return DB::transaction(function () use ($idUsuario, $nombre, $cargo, $actor) {
            DB::table('autoridad_documental')->insertOrIgnore(['clave' => 'principal', 'id_usuario' => $idUsuario, 'nombre_firmante' => $nombre, 'cargo' => $cargo, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $a = AutoridadDocumental::query()->where('clave', 'principal')->lockForUpdate()->firstOrFail();
            $cuenta = AuthUsuario::query()->lockForUpdate()->findOrFail($idUsuario);
            if ($cuenta->id_estado !== 'ACTIVO') {
                throw DominioException::conflicto('AUTORIDAD_DOCUMENTAL_INVALIDA', 'La cuenta del titular no está activa.');
            }
            $anterior = $a->version > 0 ? $a->id_usuario : null;
            // Exclusividad: retira el perfil dedicado de cualquier otra cuenta.
            $otros = AuthUsuarioPerfil::query()->where('codigo_perfil', self::PERFIL_AUTORIDAD)->where('id_usuario', '!=', $idUsuario)->pluck('id_usuario');
            DB::table('auth_usuario_perfil')->where('codigo_perfil', self::PERFIL_AUTORIDAD)->where('id_usuario', '!=', $idUsuario)->delete();
            if (! DB::table('auth_usuario_perfil')->where(['id_usuario' => $idUsuario, 'codigo_perfil' => self::PERFIL_AUTORIDAD])->exists()) {
                AuthUsuarioPerfil::create(['id_usuario' => $idUsuario, 'codigo_perfil' => self::PERFIL_AUTORIDAD, 'creado_por' => $actor]);
            }
            DB::table('auth_usuario_perfil')->where(['id_usuario' => $idUsuario, 'codigo_perfil' => self::PERFIL_AUTORIDAD])->update(['deleted_at' => null]);
            $a->update(['id_usuario' => $idUsuario, 'nombre_firmante' => $nombre, 'cargo' => $cargo, 'version' => $a->version + 1, 'asignado_por' => $actor]);
            AutoridadDocumentalHistorial::create(['id_usuario_anterior' => $anterior, 'id_usuario_nuevo' => $idUsuario, 'cargo' => $cargo, 'version' => $a->version, 'asignado_por' => $actor, 'instante' => now()]);
            foreach ($otros->push($idUsuario) as $u) {
                $this->permisos->invalidarUsuario($u);
            }
            $this->auditoria->registrar($actor, 'autoridad.asignar', 'autoridad_documental', $a->id_autoridad, null, ['anterior' => $anterior, 'nuevo' => $idUsuario]);

            return $a->fresh();
        });
    }

    /**
     * Comprobación defensiva: exactamente un titular activo con concesión EXPLÍCITA del permiso
     * mediante perfiles no técnicos (el permiso root implícito no identifica firmante).
     */
    public function titularValido(): AutoridadDocumental
    {
        $a = AutoridadDocumental::query()->where('clave', 'principal')->where('version', '>', 0)->first();
        $concesiones = DB::table('auth_usuario_perfil as up')
            ->join('seguridad_perfil as p', 'p.codigo', '=', 'up.codigo_perfil')
            ->join('seguridad_perfil_accion as pa', 'pa.codigo_perfil', '=', 'p.codigo')
            ->join('seguridad_accion as ac', 'ac.id_accion', '=', 'pa.id_accion')
            ->join('seguridad_permiso_accion as spa', 'spa.id_accion', '=', 'ac.id_accion')
            ->join('auth_usuario as u', 'u.id_usuario', '=', 'up.id_usuario')
            ->whereNull('up.deleted_at')->where('p.activo', true)->where('ac.activo', true)
            ->where('spa.permiso', self::PERMISO_AUTORIDAD)->where('u.id_estado', 'ACTIVO')
            ->distinct()->pluck('up.id_usuario');
        if (! $a || $concesiones->count() !== 1 || $concesiones->first() !== $a->id_usuario) {
            throw DominioException::conflicto('AUTORIDAD_DOCUMENTAL_INVALIDA', 'Debe existir exactamente una máxima autoridad activa con el permiso exclusivo.', ['titulares' => $concesiones->count()]);
        }

        return $a;
    }

    // ---------------- Emisión ----------------

    /**
     * Emite (o reutiliza) un documento. Devuelve [documento, creado].
     *
     * @return array{0: DocumentoExpediente, 1: bool}
     */
    public function emitir(Expediente $exp, string $tipo, string $clave, string $emisor): array
    {
        $existente = DocumentoExpediente::query()->where('id_emisor', $emisor)->where('clave_idempotencia', $clave)->first();
        if ($existente) {
            if ((int) $existente->id_expediente !== (int) $exp->id_expediente || $existente->tipo !== $tipo) {
                throw DominioException::conflicto('IDEMPOTENCIA_CONFLICTO', 'La clave de idempotencia ya se usó con otro contenido.');
            }
            if ($existente->estado === 'fallido') {
                $existente->update(['estado' => 'pendiente', 'codigo_error' => null, 'mensaje_error' => null]);
                GenerarDocumentoJob::dispatch($existente->id_documento)->afterCommit();
            }

            return [$existente->fresh(), false];
        }
        $identidad = $this->identidadInstitucional();
        $autoridad = $this->titularValido();
        $instantanea = $this->instantanea($exp, $tipo);

        $doc = DB::transaction(fn () => DocumentoExpediente::create([
            'id_expediente' => $exp->id_expediente,
            'tipo' => $tipo,
            'folio' => strtoupper($tipo === 'certificacion_notas' ? 'CN' : 'CE').'-'.now()->format('Y').'-'.strtoupper(Str::random(8)),
            'estado' => 'pendiente',
            'id_emisor' => $emisor,
            'clave_idempotencia' => $clave,
            'instantanea' => $instantanea,
            'identidad_institucional' => $identidad,
            'id_usuario_autoridad' => $autoridad->id_usuario,
            'nombre_autoridad' => $autoridad->nombre_firmante,
            'cargo_autoridad' => $autoridad->cargo,
            'version_autoridad' => $autoridad->version,
            'version_plantilla' => self::VERSION_PLANTILLA,
        ]));
        $this->auditoria->registrar($emisor, 'documento.emitir', 'documento_expediente', $doc->id_documento, null, ['tipo' => $tipo, 'folio' => $doc->folio]);
        GenerarDocumentoJob::dispatch($doc->id_documento)->afterCommit();

        return [$doc, true];
    }

    /**
     * Worker: verifica autoridad, renderiza PDF y confirma bajo bloqueo.
     */
    public function generar(int $idDocumento): void
    {
        $doc = DocumentoExpediente::find($idDocumento);
        if (! $doc || $doc->estado === 'emitido') {
            return;
        }
        $doc->increment('intentos');
        try {
            $autoridad = $this->titularValido();
            if ($autoridad->id_usuario !== $doc->id_usuario_autoridad || (int) $autoridad->version !== (int) $doc->version_autoridad) {
                // Renueva solo el firmante, conservando el evento del intento anterior.
                $this->auditoria->registrar(null, 'documento.autoridad_cambio', 'documento_expediente', $doc->id_documento, null, ['anterior' => $doc->id_usuario_autoridad]);
                $doc->update(['estado' => 'fallido', 'codigo_error' => 'AUTORIDAD_DOCUMENTAL_CAMBIO', 'mensaje_error' => 'La autoridad cambió durante la generación; reintenta la emisión.',
                    'id_usuario_autoridad' => $autoridad->id_usuario, 'nombre_autoridad' => $autoridad->nombre_firmante, 'cargo_autoridad' => $autoridad->cargo, 'version_autoridad' => $autoridad->version]);

                return;
            }
            [$pdf, $paginas] = $this->renderizar($doc);
            $ruta = 'documentos/'.$doc->id_expediente.'/'.$doc->folio.'.pdf';
            Storage::disk(self::DISCO)->put($ruta, $pdf);
            DB::transaction(function () use ($doc, $ruta, $paginas) {
                AutoridadDocumental::query()->where('clave', 'principal')->lockForUpdate()->first();
                $a = $this->titularValido();
                $fila = DocumentoExpediente::query()->lockForUpdate()->findOrFail($doc->id_documento);
                if ($a->id_usuario !== $fila->id_usuario_autoridad) {
                    Storage::disk(self::DISCO)->delete($ruta);
                    throw DominioException::conflicto('AUTORIDAD_DOCUMENTAL_CAMBIO', 'La autoridad cambió durante la generación.');
                }
                $fila->update(['estado' => 'emitido', 'ruta_archivo' => $ruta, 'paginas' => $paginas, 'emitido_en' => now(), 'codigo_error' => null, 'mensaje_error' => null]);
            });
        } catch (DominioException $e) {
            $doc->update(['estado' => 'fallido', 'codigo_error' => $e->codigo, 'mensaje_error' => $e->getMessage()]);
        }
    }

    /**
     * Identidad institucional desde la marca blanca. Falta de nombre o logo → error previsto.
     *
     * @return array<string, mixed>
     */
    public function identidadInstitucional(): array
    {
        $marca = $this->personalizacion->publica();
        $nombre = $marca['textos']['nombre'] ?? null;
        $fila = AppPersonalizacion::find('plataforma');
        $logo = $fila?->assets['logo'] ?? null;
        if (! $nombre || ! $logo) {
            throw DominioException::conflicto('IDENTIDAD_INSTITUCIONAL_INCOMPLETA', 'Configura el nombre y el logo institucional en Personalización.');
        }
        $contenido = Storage::disk(PersonalizacionService::DISCO)->get($logo['ruta']);

        return ['nombre' => $nombre, 'logo_mime' => $logo['mime'], 'logo_base64' => base64_encode((string) $contenido), 'logo_hash' => $logo['hash']];
    }

    /**
     * Datos certificados congelados para la emisión.
     *
     * @return array<string, mixed>
     */
    private function instantanea(Expediente $exp, string $tipo): array
    {
        $est = $exp->estudiante;
        $base = [
            'carnet' => $est->carnet,
            'nombre' => trim($est->nombres.' '.$est->primer_apellido.' '.($est->segundo_apellido ?? '')),
            'carrera' => $exp->carrera->nombre,
            'codigo_carrera' => $exp->carrera->codigo,
            'pensum' => $exp->pensum->version_curricular,
            'estado' => ExpedienteEstados::ETIQUETAS[$exp->estado],
            'fecha_ingreso' => $exp->fecha_ingreso?->toDateString(),
            'lugar' => 'San Salvador',
            'fecha' => now()->setTimezone(app(ConfiguracionService::class)->zona())->format('Y-m-d'),
        ];
        if ($tipo === 'certificacion_notas') {
            $res = $this->historial->publicados($exp->id_expediente);
            if ($res->isEmpty()) {
                throw DominioException::conflicto('DOCUMENTO_NO_EMITIBLE', 'El expediente no tiene resultados publicados para certificar.');
            }

            return $base + ['resultados' => $res->map(fn ($r) => [
                'ciclo' => $r->ciclo_codigo, 'codigo' => $r->materia_codigo, 'materia' => $r->materia_nombre,
                'nota' => number_format((float) $r->nota_final, 2), 'condicion' => $r->condicion === 'aprobada' ? 'Aprobada' : 'Reprobada',
                'creditos' => (float) $r->creditos, 'politica' => 'v'.$r->version_politica,
            ])->values()->all(), 'creditos_aprobados' => (float) $res->where('condicion', 'aprobada')->sum('creditos')];
        }
        if ($exp->estado !== ExpedienteEstados::ACTIVO) {
            throw DominioException::conflicto('DOCUMENTO_NO_EMITIBLE', 'La constancia requiere un expediente Activo.');
        }
        $ahora = now('UTC');
        $vigente = Inscripcion::query()->where('id_expediente', $exp->id_expediente)->where('estado', 'activa')
            ->whereHas('ciclo', fn ($q) => $q->where('estado', 'publicado')->where('inicio', '<=', $ahora)->where('fin', '>', $ahora))
            ->with('ciclo', 'materia')->get();
        if ($vigente->isEmpty()) {
            throw DominioException::conflicto('DOCUMENTO_NO_EMITIBLE', 'La constancia requiere inscripción activa en un ciclo vigente.');
        }

        return $base + ['ciclo' => $vigente->first()->ciclo->nombre, 'materias_inscritas' => $vigente->map(fn ($i) => ['codigo' => $i->materia->codigo, 'materia' => $i->materia->nombre])->values()->all()];
    }

    /**
     * Renderiza el PDF A4 con encabezado (logo izquierda, nombre centrado), paginación y espacio de firma.
     *
     * @return array{0: string, 1: int}
     */
    public function renderizar(DocumentoExpediente $doc): array
    {
        $html = view('documentos.academico', ['doc' => $doc, 'i' => $doc->instantanea, 'inst' => $doc->identidad_institucional])->render();
        $opciones = new Options;
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $paginas = $canvas->get_page_count();
        $fuente = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(270, 815, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 9, [0, 0, 0]);

        return [$pdf->output(), $paginas];
    }

    /**
     * @return array<string, mixed>
     */
    public function json(DocumentoExpediente $d): array
    {
        return [
            'id_documento' => $d->id_documento, 'tipo' => $d->tipo, 'folio' => $d->folio, 'estado' => $d->estado,
            'autoridad' => ['nombre' => $d->nombre_autoridad, 'cargo' => $d->cargo_autoridad],
            'paginas' => $d->paginas, 'codigo_error' => $d->codigo_error, 'mensaje_error' => $d->mensaje_error,
            'emitido_en' => $d->emitido_en?->toIso8601String(), 'creado_en' => $d->created_at?->toIso8601String(),
        ];
    }
}
