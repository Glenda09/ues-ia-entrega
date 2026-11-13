<?php

namespace App\Services\Academico;

use App\Jobs\Academico\EnviarEntregaAccesoJob;
use App\Models\Academico\ArchivoIngreso;
use App\Models\Academico\BorradorIngreso;
use App\Models\Academico\Carrera;
use App\Models\Academico\CarreraOfertaAdmision;
use App\Models\Academico\ConvocatoriaIngreso;
use App\Models\Academico\CorreccionIngreso;
use App\Models\Academico\OrdenPagoEvento;
use App\Models\Academico\OrdenPagoIngreso;
use App\Models\Academico\RequisitoIngreso;
use App\Models\Academico\SolicitudAnexo;
use App\Models\Academico\SolicitudIngreso;
use App\Models\Academico\SolicitudIngresoRevision;
use App\Models\Academico\VerificacionCorreoIngreso;
use App\Models\Auth\AuthUsuario;
use App\Support\Academico\DominioException;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * EXP-01: registro verificado, borrador por etapas, anexos privados, orden de pago y envío.
 */
class AdmisionService
{
    public const DISCO = 'local';

    /** Campos estructurados aceptados en el borrador (FORMULARIO.md). */
    public const CAMPOS = [
        'nombres', 'primer_apellido', 'segundo_apellido', 'fecha_nacimiento', 'nacionalidad', 'dui', 'nie', 'pais_documento', 'pasaporte',
        'telefono_celular', 'departamento', 'municipio_distrito', 'direccion', 'sexo_genero', 'estado_civil', 'contacto_emergencia',
        'institucion_procedencia', 'tipo_bachillerato', 'estado_graduacion', 'anio_graduacion', 'tipo_prueba', 'resultado_paes_avanzo',
        'modalidad_estudio', 'id_sede', 'id_facultad', 'id_carrera', 'modalidad_ingreso', 'referencia_comprobante',
    ];

    public function __construct(
        private ConfiguracionService $configuracion,
        private ConvocatoriaService $convocatorias,
        private IdentidadAccesoService $identidad,
        private AuditoriaService $auditoria,
        private CatalogoService $catalogo,
    ) {}

    // ---------------- Registro verificado ----------------

    /**
     * Solicita código de verificación. Respuesta genérica (anti-enumeración). 3 envíos/hora por correo e IP.
     */
    public function solicitarVerificacion(string $email, string $ip): void
    {
        $email = IdentidadAccesoService::normalizarClave($email);
        foreach (['correo:'.$email, 'ip:'.$ip] as $k) {
            if (RateLimiter::tooManyAttempts('verif-ingreso:'.$k, 3)) {
                throw new DominioException('LIMITE_FRECUENCIA', 'Demasiadas solicitudes. Intenta más tarde.', 429, ['retry_after' => RateLimiter::availableIn('verif-ingreso:'.$k)]);
            }
        }
        foreach (['correo:'.$email, 'ip:'.$ip] as $k) {
            RateLimiter::hit('verif-ingreso:'.$k, 3600);
        }
        if (AuthUsuario::query()->where('email', $email)->exists()) {
            return; // Cuenta existente: acceso por login/recuperación; misma respuesta.
        }
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $entrega = DB::transaction(function () use ($email, $codigo, $ip) {
            VerificacionCorreoIngreso::query()->where('email', $email)->whereNull('consumido_en')->whereNull('revocado_en')->update(['revocado_en' => now()]);
            VerificacionCorreoIngreso::create(['email' => $email, 'codigo_hash' => hash('sha256', $email.'|'.$codigo), 'expira_en' => now()->addMinutes(15), 'ip' => $ip]);

            return $this->identidad->prepararEntrega('537b8b56-341c-4322-869a-de00d11cf10e', 'verificacion', $email, [], $codigo);
        });
        EnviarEntregaAccesoJob::dispatch($entrega->id_entrega)->afterCommit();
    }

    /**
     * Consume el código y crea la cuenta aspirante verificada. Resultado genérico si el correo ya tiene cuenta.
     *
     * @return array{creada: bool}
     */
    public function registrar(string $email, string $codigo, string $password): array
    {
        $email = IdentidadAccesoService::normalizarClave($email);
        $invalido = fn () => ValidationException::withMessages(['codigo_verificacion' => 'El código es inválido o venció.']);
        $vigente = fn () => VerificacionCorreoIngreso::query()->where('email', $email)->whereNull('consumido_en')->whereNull('revocado_en')->orderByDesc('id_verificacion');

        $previa = $vigente()->first();
        if ($previa && ! hash_equals($previa->codigo_hash, hash('sha256', $email.'|'.$codigo))) {
            $previa->increment('intentos');
            throw $invalido();
        }

        return DB::transaction(function () use ($email, $password, $vigente, $invalido, $codigo) {
            $v = $vigente()->lockForUpdate()->first();
            if (! $v || $v->expira_en->isPast() || $v->intentos >= 5 || ! hash_equals($v->codigo_hash, hash('sha256', $email.'|'.$codigo))) {
                throw $invalido();
            }
            $v->update(['consumido_en' => now()]);
            if (AuthUsuario::query()->where('email', $email)->exists()) {
                return ['creada' => false];
            }
            if (! $this->identidad->identificadorDisponible($email)) {
                return ['creada' => false];
            }
            $u = AuthUsuario::create([
                'username' => $email,
                'email' => $email,
                'password' => $password,
                'fecha_verificacion' => now(),
                'id_estado' => 'ACTIVO',
            ]);
            $this->identidad->reservarIdentificador($email, $u->id_usuario);
            $this->catalogo->asegurarPerfil($u->id_usuario, 'academico_aspirante', $u->id_usuario);
            $this->auditoria->registrar($u->id_usuario, 'admision.registro', 'auth_usuario', $u->id_usuario);

            return ['creada' => true];
        });
    }

    /**
     * Vincula el perfil aspirante a una cuenta existente autenticada.
     */
    public function vincularAspirante(string $idUsuario): void
    {
        $this->catalogo->asegurarPerfil($idUsuario, 'academico_aspirante', $idUsuario);
    }

    // ---------------- Borrador ----------------

    public function convocatoriaAbierta(int $idConvocatoria): ConvocatoriaIngreso
    {
        $c = ConvocatoriaIngreso::query()->findOrFail($idConvocatoria);
        if (! $this->convocatorias->abierta($c)) {
            throw DominioException::conflicto('CONVOCATORIA_CERRADA', 'La convocatoria no está abierta para recibir solicitudes.');
        }

        return $c;
    }

    public function borrador(string $idUsuario, int $idConvocatoria): ?BorradorIngreso
    {
        return BorradorIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $idConvocatoria)->first();
    }

    /**
     * Crea (version=0) o actualiza el borrador con versión optimista. Al completar el paso 3 genera la orden de pago.
     */
    public function guardarBorrador(string $idUsuario, int $idConvocatoria, int $version, int $etapa, array $datos): BorradorIngreso
    {
        $conv = $this->convocatoriaAbierta($idConvocatoria);
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS);
        if ($desconocidos) {
            throw ValidationException::withMessages(collect($desconocidos)->mapWithKeys(fn ($k) => ["datos.$k" => 'Campo no permitido.'])->all());
        }

        return DB::transaction(function () use ($idUsuario, $conv, $version, $etapa, $datos) {
            $b = BorradorIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $conv->id_convocatoria)->lockForUpdate()->first();
            if (! $b) {
                if ($version !== 0) {
                    throw DominioException::conflicto('VERSION_CONFLICTO', 'El borrador no existe; envía version=0 para crearlo.');
                }
                try {
                    $b = BorradorIngreso::create(['id_usuario' => $idUsuario, 'id_convocatoria' => $conv->id_convocatoria, 'etapa' => $etapa, 'datos' => $datos, 'anexos' => [], 'version' => 1]);
                } catch (UniqueConstraintViolationException) {
                    throw DominioException::conflicto('VERSION_CONFLICTO', 'El borrador ya fue creado. Recarga los datos.');
                }
            } else {
                $this->catalogo->exigirVersion($b, $version);
                $b->datos = array_replace($b->datos ?? [], $datos);
                $b->etapa = max($b->etapa, $etapa);
                $b->version++;
                $b->save();
            }
            if ($b->etapa >= 3 && $conv->requiere_pago) {
                $this->validarSeleccionAcademica($conv, $b->datos);
                $this->asegurarOrdenPago($idUsuario, $conv);
            }

            return $b;
        });
    }

    /**
     * Adjunta un archivo privado al borrador por código de requisito, validando tipo real y tamaño.
     */
    public function adjuntar(string $idUsuario, int $idConvocatoria, string $codigoRequisito, UploadedFile $archivo, int $version): BorradorIngreso
    {
        $conv = $this->convocatoriaAbierta($idConvocatoria);
        $req = RequisitoIngreso::query()->where('id_convocatoria', $conv->id_convocatoria)->where('version_requisitos', $conv->version_requisitos)
            ->where('codigo', $codigoRequisito)->where('tipo', 'anexo')->first();
        if (! $req) {
            throw ValidationException::withMessages(['codigo_requisito' => 'El requisito no existe en la versión vigente.']);
        }
        $this->validarArchivo($req, $archivo);

        $contenido = $archivo->get();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime];
        $ruta = 'admision/'.$idUsuario.'/'.Str::uuid().'.'.$ext;
        Storage::disk(self::DISCO)->put($ruta, $contenido);

        try {
            return DB::transaction(function () use ($idUsuario, $conv, $req, $ruta, $mime, $contenido, $version) {
                $b = BorradorIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $conv->id_convocatoria)->lockForUpdate()->firstOrFail();
                $this->catalogo->exigirVersion($b, $version);
                $a = ArchivoIngreso::create([
                    'id_usuario' => $idUsuario, 'id_convocatoria' => $conv->id_convocatoria, 'codigo_requisito' => $req->codigo,
                    'disco' => self::DISCO, 'ruta' => $ruta, 'nombre_seguro' => $req->codigo.'.'.pathinfo($ruta, PATHINFO_EXTENSION),
                    'mime' => $mime, 'tamano' => strlen($contenido), 'hash' => hash('sha256', $contenido),
                ]);
                $anexos = $b->anexos ?? [];
                $anexos[$req->codigo] = $a->id_archivo;
                $b->anexos = $anexos;
                $b->etapa = max($b->etapa, 4);
                $b->version++;
                $b->save();
                if ($req->codigo === 'comprobante_pago') {
                    $this->registrarComprobante($idUsuario, $conv, $a->id_archivo, $b->datos['referencia_comprobante'] ?? null);
                }

                return $b;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISCO)->delete($ruta);
            throw $e;
        }
    }

    // ---------------- Envío ----------------

    /**
     * Confirma el envío: valida ventana, versión de requisitos, campos y anexos aplicables; crea solicitud y revisión 1.
     *
     * @return array{0:int,1:array}
     */
    public function enviar(string $idUsuario, int $idConvocatoria, int $versionRequisitos): array
    {
        return DB::transaction(function () use ($idUsuario, $idConvocatoria, $versionRequisitos) {
            $conv = ConvocatoriaIngreso::query()->lockForUpdate()->findOrFail($idConvocatoria);
            if (! $this->convocatorias->abierta($conv)) {
                throw DominioException::conflicto('CONVOCATORIA_CERRADA', 'La convocatoria no está abierta para recibir solicitudes.');
            }
            $existente = SolicitudIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $idConvocatoria)->first();
            if ($existente) {
                return [200, $this->solicitudPropiaJson($existente)];
            }
            if ($versionRequisitos !== (int) $conv->version_requisitos) {
                throw DominioException::conflicto('CONVOCATORIA_ACTUALIZADA', 'Los requisitos cambiaron. Recarga el formulario.', ['version_requisitos' => (int) $conv->version_requisitos]);
            }
            $b = BorradorIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $idConvocatoria)->lockForUpdate()->first();
            if (! $b) {
                throw ValidationException::withMessages(['borrador' => 'Completa el formulario antes de enviar.']);
            }
            $u = AuthUsuario::query()->findOrFail($idUsuario);
            if (! $u->fecha_verificacion) {
                throw ValidationException::withMessages(['email' => 'La cuenta no está verificada.']);
            }
            $datos = $b->datos ?? [];
            [$aplicables, $edad, $ctx] = $this->evaluarRequisitos($conv, $datos, $versionRequisitos);
            $this->validarDatosEnvio($conv, $datos, $aplicables, $b->anexos ?? [], 'envio');

            $orden = OrdenPagoIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $idConvocatoria)->first();
            $snapshot = $this->proyectarDatos($datos, $aplicables, $ctx);
            $solicitud = SolicitudIngreso::create([
                'id_convocatoria' => $idConvocatoria,
                'id_usuario' => $idUsuario,
                'folio' => 'SOL-'.strtoupper(Str::random(10)),
                'version_requisitos' => $versionRequisitos,
                'estado' => 'recibida',
                'nombres' => $datos['nombres'],
                'primer_apellido' => $datos['primer_apellido'],
                'segundo_apellido' => $datos['segundo_apellido'] ?? null,
                'email' => $u->email,
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'nacionalidad' => $datos['nacionalidad'],
                'documento_tipo' => $this->documentoTipo($datos),
                'documento_numero' => $this->documentoNumero($datos),
                'id_carrera' => (int) $datos['id_carrera'],
                'id_sede' => (int) $datos['id_sede'],
                'id_facultad' => (int) $datos['id_facultad'],
                'modalidad_estudio' => $datos['modalidad_estudio'],
                'modalidad_ingreso' => $datos['modalidad_ingreso'],
                'revision_actual' => 1,
            ]);
            $this->crearRevision($solicitud, 1, $snapshot + ['id_orden_pago' => $orden?->id_orden_pago], $edad, $b->anexos ?? [], $aplicables);
            $this->auditoria->registrar($idUsuario, 'solicitud.enviar', 'solicitud_ingreso', $solicitud->id_solicitud, null, ['folio' => $solicitud->folio, 'version_requisitos' => $versionRequisitos]);

            return [201, $this->solicitudPropiaJson($solicitud)];
        });
    }

    /**
     * Corrección con token de un solo uso: nueva revisión y vuelta a `recibida`.
     */
    public function corregir(string $token, array $datos, array $archivos): SolicitudIngreso
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS);
        if ($desconocidos) {
            throw ValidationException::withMessages(collect($desconocidos)->mapWithKeys(fn ($k) => ["datos.$k" => 'Campo no permitido.'])->all());
        }
        $guardados = [];
        try {
            return DB::transaction(function () use ($token, $datos, $archivos, &$guardados) {
                $corr = CorreccionIngreso::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
                if (! $corr || $corr->consumido_en || $corr->revocado_en || $corr->expira_en->isPast()) {
                    throw DominioException::conflicto('CORRECCION_INVALIDA', 'El enlace de corrección es inválido, ya se usó o venció.');
                }
                $sol = SolicitudIngreso::query()->lockForUpdate()->findOrFail($corr->id_solicitud);
                $conv = ConvocatoriaIngreso::query()->findOrFail($sol->id_convocatoria);
                if ($sol->estado !== 'observada') {
                    throw DominioException::conflicto('CORRECCION_INVALIDA', 'La solicitud no está observada.');
                }
                if (! $this->configuracion->valor('admision.correccion_tras_cierre') && ($conv->estado === 'cerrada' || ($conv->fin && $conv->fin->isPast()))) {
                    throw DominioException::conflicto('CORRECCION_INVALIDA', 'La convocatoria cerró y no admite correcciones.');
                }
                $ultima = SolicitudIngresoRevision::query()->where('id_solicitud', $sol->id_solicitud)->where('revision', $sol->revision_actual)->firstOrFail();
                $base = $ultima->datos['formulario'] ?? [];
                $nuevos = array_replace($base, $datos);
                $anexos = SolicitudAnexo::query()->where('id_revision', $ultima->id_revision)->pluck('id_archivo', 'codigo_requisito')->all();
                foreach ($archivos as $codigo => $archivo) {
                    $req = RequisitoIngreso::query()->where('id_convocatoria', $conv->id_convocatoria)->where('version_requisitos', $sol->version_requisitos)->where('codigo', $codigo)->where('tipo', 'anexo')->first();
                    if (! $req) {
                        throw ValidationException::withMessages(["archivos.$codigo" => 'Requisito inexistente.']);
                    }
                    $this->validarArchivo($req, $archivo);
                    $contenido = $archivo->get();
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
                    $ruta = 'admision/'.$sol->id_usuario.'/'.Str::uuid().'.'.['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime];
                    Storage::disk(self::DISCO)->put($ruta, $contenido);
                    $guardados[] = $ruta;
                    $a = ArchivoIngreso::create([
                        'id_usuario' => $sol->id_usuario, 'id_convocatoria' => $conv->id_convocatoria, 'codigo_requisito' => $codigo, 'disco' => self::DISCO,
                        'ruta' => $ruta, 'nombre_seguro' => $codigo.'.'.pathinfo($ruta, PATHINFO_EXTENSION), 'mime' => $mime, 'tamano' => strlen($contenido), 'hash' => hash('sha256', $contenido),
                    ]);
                    $anexos[$codigo] = $a->id_archivo;
                }
                [$aplicables, $edad, $ctx] = $this->evaluarRequisitos($conv, $nuevos, (int) $sol->version_requisitos);
                $this->validarDatosEnvio($conv, $nuevos, $aplicables, $anexos, 'envio');
                $rev = $sol->revision_actual + 1;
                $this->crearRevision($sol, $rev, $this->proyectarDatos($nuevos, $aplicables, $ctx) + ['id_orden_pago' => $ultima->datos['id_orden_pago'] ?? null], $edad, $anexos, $aplicables);
                $sol->fill([
                    'revision_actual' => $rev, 'estado' => 'recibida', 'nombres' => $nuevos['nombres'], 'primer_apellido' => $nuevos['primer_apellido'],
                    'segundo_apellido' => $nuevos['segundo_apellido'] ?? null, 'fecha_nacimiento' => $nuevos['fecha_nacimiento'], 'nacionalidad' => $nuevos['nacionalidad'],
                    'documento_tipo' => $this->documentoTipo($nuevos), 'documento_numero' => $this->documentoNumero($nuevos),
                ]);
                $sol->version++;
                $sol->save();
                $corr->update(['consumido_en' => now()]);
                $this->auditoria->registrar($sol->id_usuario, 'solicitud.corregir', 'solicitud_ingreso', $sol->id_solicitud, null, ['revision' => $rev]);

                return $sol;
            });
        } catch (\Throwable $e) {
            foreach ($guardados as $r) {
                Storage::disk(self::DISCO)->delete($r);
            }
            throw $e;
        }
    }

    // ---------------- Pago ----------------

    public function asegurarOrdenPago(string $idUsuario, ConvocatoriaIngreso $conv): OrdenPagoIngreso
    {
        DB::table('orden_pago_ingreso')->insertOrIgnore([
            'id_usuario' => $idUsuario, 'id_convocatoria' => $conv->id_convocatoria,
            'folio' => 'OP-'.$conv->id_convocatoria.'-'.strtoupper(Str::random(8)),
            'monto' => $conv->monto_pago ?? $this->configuracion->valor('admision.monto_pago_usd'),
            'estado' => 'pendiente', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return OrdenPagoIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $conv->id_convocatoria)->firstOrFail();
    }

    private function registrarComprobante(string $idUsuario, ConvocatoriaIngreso $conv, int $idArchivo, ?string $referencia): void
    {
        $o = OrdenPagoIngreso::query()->where('id_usuario', $idUsuario)->where('id_convocatoria', $conv->id_convocatoria)->lockForUpdate()->first();
        if (! $o) {
            throw DominioException::conflicto('ORDEN_PAGO_INEXISTENTE', 'Completa la información académica para generar la orden de pago.');
        }
        if ($o->estado === 'validado') {
            throw DominioException::conflicto('PAGO_VALIDADO', 'El pago ya fue validado.');
        }
        OrdenPagoEvento::create(['id_orden_pago' => $o->id_orden_pago, 'estado_anterior' => $o->estado, 'estado_nuevo' => 'comprobante_recibido', 'id_archivo' => $idArchivo, 'id_usuario' => $idUsuario, 'instante' => now()]);
        $o->update(['estado' => 'comprobante_recibido', 'id_archivo_comprobante' => $idArchivo, 'referencia_comprobante' => $referencia, 'version' => $o->version + 1]);
    }

    /**
     * Revisión del pago por el gestor: comprobante_recibido → validado | observado.
     */
    public function revisarPago(SolicitudIngreso $sol, string $estado, int $version, ?string $motivo, string $actor): OrdenPagoIngreso
    {
        return DB::transaction(function () use ($sol, $estado, $version, $motivo, $actor) {
            $o = OrdenPagoIngreso::query()->where('id_usuario', $sol->id_usuario)->where('id_convocatoria', $sol->id_convocatoria)->lockForUpdate()->first();
            if (! $o) {
                throw DominioException::conflicto('ORDEN_PAGO_INEXISTENTE', 'La solicitud no tiene orden de pago.');
            }
            if ($o->estado === $estado) {
                return $o;
            }
            $this->catalogo->exigirVersion($o, $version);
            if ($o->estado !== 'comprobante_recibido') {
                throw DominioException::conflicto('TRANSICION_INVALIDA', 'Solo se revisa un comprobante recibido.');
            }
            if ($estado === 'observado' && ! $motivo) {
                throw ValidationException::withMessages(['motivo' => 'La observación requiere motivo.']);
            }
            OrdenPagoEvento::create(['id_orden_pago' => $o->id_orden_pago, 'estado_anterior' => $o->estado, 'estado_nuevo' => $estado, 'motivo' => $motivo, 'id_usuario' => $actor, 'instante' => now()]);
            $o->update(['estado' => $estado, 'motivo_observacion' => $motivo, 'version' => $o->version + 1]);
            $this->auditoria->registrar($actor, 'pago.revisar', 'orden_pago_ingreso', $o->id_orden_pago, $motivo, ['estado' => $estado]);

            return $o;
        });
    }

    // ---------------- Reglas ----------------

    /**
     * @return array{0: Collection<int, RequisitoIngreso>, 1: int, 2: array<string, mixed>}
     */
    public function evaluarRequisitos(ConvocatoriaIngreso $conv, array $datos, int $version): array
    {
        $zona = $this->configuracion->zona();
        $edad = 0;
        if (! empty($datos['fecha_nacimiento']) && strtotime($datos['fecha_nacimiento'])) {
            $edad = CarbonImmutable::parse($datos['fecha_nacimiento'], $zona)->diffInYears(CarbonImmutable::now($zona)->startOfDay());
            $edad = (int) floor($edad);
        }
        $carrera = Carrera::find($datos['id_carrera'] ?? $conv->id_carrera);
        $ctx = [
            'modalidad_ingreso' => $datos['modalidad_ingreso'] ?? null,
            'modalidad_estudio' => $datos['modalidad_estudio'] ?? null,
            'nacionalidad' => isset($datos['nacionalidad']) ? mb_strtolower($datos['nacionalidad']) : null,
            'mayor_edad' => $edad >= (int) $this->configuracion->valor('admision.mayoria_edad', 18),
            'estado_graduacion' => $datos['estado_graduacion'] ?? null,
            'id_carrera' => isset($datos['id_carrera']) ? (int) $datos['id_carrera'] : null,
            'clasificacion_carrera' => $carrera?->clasificacion,
        ];
        $aplicables = $this->convocatorias->requisitos($conv, $version)
            ->filter(fn ($r) => ConvocatoriaService::evaluarCondicion($r->condicion, $ctx))
            ->reject(fn ($r) => $r->codigo === 'comprobante_pago' && ! $conv->requiere_pago)
            ->values();

        return [$aplicables, $edad, $ctx];
    }

    private function validarDatosEnvio(ConvocatoriaIngreso $conv, array $d, $aplicables, array $anexos, string $etapa): void
    {
        $e = [];
        foreach (['nombres', 'primer_apellido', 'fecha_nacimiento', 'nacionalidad', 'modalidad_estudio', 'modalidad_ingreso', 'id_sede', 'id_facultad', 'id_carrera', 'institucion_procedencia', 'estado_graduacion'] as $c) {
            if (empty($d[$c])) {
                $e["datos.$c"][] = 'Campo obligatorio.';
            }
        }
        if (! empty($d['fecha_nacimiento']) && (! strtotime($d['fecha_nacimiento']) || $d['fecha_nacimiento'] >= now()->toDateString())) {
            $e['datos.fecha_nacimiento'][] = 'Fecha inválida.';
        }
        if (! empty($d['estado_graduacion']) && ! in_array($d['estado_graduacion'], ['graduado', 'pendiente'], true)) {
            $e['datos.estado_graduacion'][] = 'Valor inválido.';
        }
        $esSalv = mb_strtolower($d['nacionalidad'] ?? '') === 'salvadoreña';
        if (! $esSalv && ! empty($d['nacionalidad']) && (empty($d['pais_documento']) || empty($d['pasaporte']))) {
            $e['datos.pasaporte'][] = 'El aspirante extranjero requiere país y número de pasaporte.';
        }
        foreach ($aplicables as $r) {
            if (! $r->obligatorio) {
                continue;
            }
            // Título de graduados se exige al enviar; pendientes de graduación hasta la aceptación.
            $exigeAhora = $r->etapa === $etapa || ($r->codigo === 'titulo_bachiller' && ($d['estado_graduacion'] ?? null) === 'graduado');
            if (! $exigeAhora) {
                continue;
            }
            if ($r->tipo === 'campo') {
                if (empty($d[$r->codigo])) {
                    $e["datos.{$r->codigo}"][] = "{$r->nombre} es obligatorio.";
                }
            } elseif (empty($anexos[$r->codigo])) {
                $e["anexos.{$r->codigo}"][] = "Falta el documento: {$r->nombre}.";
            }
        }
        if ($esSalv && collect($aplicables)->contains('codigo', 'dui') && empty($d['dui'])) {
            $e['datos.dui'][] = 'El número de DUI es obligatorio.';
        } elseif (! empty($d['dui']) && ! preg_match('/^\d{8}-\d$/', $d['dui'])) {
            $e['datos.dui'][] = 'Formato de DUI inválido (########-#).';
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }
        $this->validarSeleccionAcademica($conv, $d);
    }

    private function validarSeleccionAcademica(ConvocatoriaIngreso $conv, array $d): void
    {
        if (empty($d['id_carrera']) || empty($d['id_sede']) || empty($d['id_facultad']) || empty($d['modalidad_estudio']) || empty($d['modalidad_ingreso'])) {
            throw ValidationException::withMessages(['datos.id_carrera' => 'Completa la selección académica (sede, facultad, carrera y modalidades).']);
        }
        $ok = CarreraOfertaAdmision::query()->where([
            'id_convocatoria' => $conv->id_convocatoria, 'id_carrera' => $d['id_carrera'], 'id_sede' => $d['id_sede'],
            'id_facultad' => $d['id_facultad'], 'modalidad_estudio' => $d['modalidad_estudio'], 'modalidad_ingreso' => $d['modalidad_ingreso'],
        ])->exists();
        if (! $ok) {
            throw ValidationException::withMessages(['datos.id_carrera' => 'La combinación académica no está habilitada en la convocatoria.']);
        }
    }

    private function validarArchivo(RequisitoIngreso $req, UploadedFile $archivo): void
    {
        $max = (int) ($req->max_bytes ?: $this->configuracion->valor('admision.max_bytes_archivo'));
        $contenido = $archivo->get();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        $permitidos = $req->codigo === 'fotografia' ? ['image/jpeg'] : ($req->tipos_permitidos ?: ['application/pdf', 'image/jpeg', 'image/png']);
        $ext = strtolower($archivo->getClientOriginalExtension());
        $extOk = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']];
        if (! in_array($mime, $permitidos, true) || ! in_array($ext, $extOk[$mime] ?? [], true)) {
            throw ValidationException::withMessages(['archivo' => 'Tipo de archivo no permitido para '.$req->nombre.'.']);
        }
        if (strlen($contenido) > $max) {
            throw ValidationException::withMessages(['archivo' => 'El archivo excede el tamaño máximo permitido.']);
        }
        if ($req->codigo === 'fotografia') {
            $dim = @getimagesizefromstring($contenido);
            if (! $dim || $dim[0] !== 150 || $dim[1] !== 195) {
                throw ValidationException::withMessages(['archivo' => 'La fotografía debe ser JPG de 150 × 195 píxeles.']);
            }
        }
    }

    private function proyectarDatos(array $datos, $aplicables, array $ctx): array
    {
        $codigosCampo = collect($aplicables)->where('tipo', 'campo')->pluck('codigo')->all();
        $base = ['nombres', 'primer_apellido', 'segundo_apellido', 'fecha_nacimiento', 'nacionalidad', 'telefono_celular', 'departamento', 'municipio_distrito', 'direccion',
            'sexo_genero', 'estado_civil', 'contacto_emergencia', 'institucion_procedencia', 'tipo_bachillerato', 'estado_graduacion', 'anio_graduacion', 'tipo_prueba',
            'resultado_paes_avanzo', 'modalidad_estudio', 'id_sede', 'id_facultad', 'id_carrera', 'modalidad_ingreso', 'referencia_comprobante'];
        if ($ctx['mayor_edad'] && $ctx['nacionalidad'] === 'salvadoreña') {
            $base[] = 'dui';
        }
        if ($ctx['nacionalidad'] !== 'salvadoreña') {
            array_push($base, 'pais_documento', 'pasaporte');
        }
        $permitidos = array_unique(array_merge($base, $codigosCampo));

        return ['formulario' => array_intersect_key($datos, array_flip($permitidos)), 'contexto' => $ctx];
    }

    private function crearRevision(SolicitudIngreso $sol, int $rev, array $datos, int $edad, array $anexos, $aplicables): void
    {
        $r = SolicitudIngresoRevision::create(['id_solicitud' => $sol->id_solicitud, 'revision' => $rev, 'datos' => $datos, 'edad' => $edad, 'instante' => now()]);
        $codigos = collect($aplicables)->where('tipo', 'anexo')->pluck('codigo')->all();
        foreach ($anexos as $codigo => $idArchivo) {
            if (in_array($codigo, $codigos, true)) {
                SolicitudAnexo::create(['id_revision' => $r->id_revision, 'codigo_requisito' => $codigo, 'id_archivo' => $idArchivo]);
            }
        }
    }

    private function documentoTipo(array $d): ?string
    {
        return ! empty($d['dui']) ? 'DUI' : (! empty($d['pasaporte']) ? 'PASAPORTE' : (! empty($d['nie']) ? 'NIE' : null));
    }

    private function documentoNumero(array $d): ?string
    {
        return ! empty($d['dui']) ? $d['dui'] : (! empty($d['pasaporte']) ? mb_strtoupper(($d['pais_documento'] ?? '').':'.$d['pasaporte']) : ($d['nie'] ?? null));
    }

    /**
     * Vista del titular: estado, observaciones visibles, requisitos pendientes. Sin notas internas ni binarios.
     *
     * @return array<string, mixed>
     */
    public function solicitudPropiaJson(SolicitudIngreso $s): array
    {
        $orden = OrdenPagoIngreso::query()->where('id_usuario', $s->id_usuario)->where('id_convocatoria', $s->id_convocatoria)->first();
        $conv = ConvocatoriaIngreso::find($s->id_convocatoria);
        $rev = SolicitudIngresoRevision::query()->where('id_solicitud', $s->id_solicitud)->where('revision', $s->revision_actual)->first();
        $pendientes = [];
        if ($conv && $rev) {
            [$aplicables] = $this->evaluarRequisitos($conv, $rev->datos['formulario'] ?? [], (int) $s->version_requisitos);
            $entregados = SolicitudAnexo::query()->where('id_revision', $rev->id_revision)->pluck('codigo_requisito')->all();
            $pendientes = $aplicables->where('tipo', 'anexo')->where('obligatorio', true)->reject(fn ($r) => in_array($r->codigo, $entregados, true))
                ->map(fn ($r) => ['codigo' => $r->codigo, 'nombre' => $r->nombre, 'etapa' => $r->etapa])->values()->all();
        }

        return [
            'id_solicitud' => $s->id_solicitud,
            'folio' => $s->folio,
            'id_convocatoria' => $s->id_convocatoria,
            'convocatoria' => $conv?->titulo_publico,
            'carrera' => Carrera::find($s->id_carrera)?->nombre,
            'estado' => $s->estado,
            'revision_actual' => $s->revision_actual,
            'observacion' => $s->estado === 'observada' ? $s->motivo_observacion : null,
            'requisitos_pendientes' => $pendientes,
            'pago' => $orden ? ['folio' => $orden->folio, 'monto' => $orden->monto, 'estado' => $orden->estado, 'observacion' => $orden->motivo_observacion] : null,
            'creado_en' => $s->created_at?->toIso8601String(),
        ];
    }

    /**
     * Elimina cargas temporales sin referencia con más de 24 horas (nunca las referenciadas por revisiones o borradores).
     */
    public function limpiarHuerfanos(): int
    {
        $referenciados = SolicitudAnexo::query()->pluck('id_archivo')->all();
        $enBorrador = BorradorIngreso::query()->pluck('anexos')->flatMap(fn ($a) => array_values($a ?? []))->all();
        $comprobantes = OrdenPagoIngreso::query()->whereNotNull('id_archivo_comprobante')->pluck('id_archivo_comprobante')->all();
        $protegidos = array_unique(array_merge($referenciados, $enBorrador, $comprobantes));
        $n = 0;
        ArchivoIngreso::query()->where('created_at', '<', now()->subDay())->whereNotIn('id_archivo', $protegidos ?: [0])->get()
            ->each(function ($a) use (&$n) {
                Storage::disk($a->disco)->delete($a->ruta);
                $a->delete();
                $n++;
            });

        return $n;
    }
}
