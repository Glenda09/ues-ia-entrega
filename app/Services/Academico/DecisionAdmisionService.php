<?php

namespace App\Services\Academico;

use App\Jobs\Academico\EnviarEntregaAccesoJob;
use App\Models\Academico\ConvocatoriaIngreso;
use App\Models\Academico\CorreccionIngreso;
use App\Models\Academico\DecisionIngreso;
use App\Models\Academico\Estudiante;
use App\Models\Academico\Expediente;
use App\Models\Academico\ExpedienteEvento;
use App\Models\Academico\OrdenPagoIngreso;
use App\Models\Academico\SolicitudAnexo;
use App\Models\Academico\SolicitudIngreso;
use App\Models\Academico\SolicitudIngresoRevision;
use App\Models\Auth\AuthUsuario;
use App\Services\Auth\PermisosEfectivosService;
use App\Support\Academico\DominioException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * EXP-02: observación con enlace de corrección y aceptación con carnet, cuenta y expediente.
 */
class DecisionAdmisionService
{
    public function __construct(
        private AdmisionService $admision,
        private PensumService $pensums,
        private IdentidadAccesoService $identidad,
        private CatalogoService $catalogo,
        private AuditoriaService $auditoria,
        private ConfiguracionService $configuracion,
        private PermisosEfectivosService $permisos,
    ) {}

    /**
     * Observa la solicitud: motivo visible, nuevo enlace de un solo uso (revoca el anterior).
     */
    public function observar(SolicitudIngreso $sol, int $version, string $motivo, string $actor): SolicitudIngreso
    {
        $entrega = null;
        $sol = DB::transaction(function () use ($sol, $version, $motivo, $actor, &$entrega) {
            $sol = SolicitudIngreso::query()->lockForUpdate()->findOrFail($sol->id_solicitud);
            if ($sol->estado === 'observada' && $sol->motivo_observacion === $motivo) {
                return $sol;
            }
            $this->catalogo->exigirVersion($sol, $version);
            if ($sol->estado !== 'recibida') {
                throw DominioException::conflicto('TRANSICION_INVALIDA', 'Solo se observa una solicitud recibida.');
            }
            $conv = ConvocatoriaIngreso::find($sol->id_convocatoria);
            CorreccionIngreso::query()->where('id_solicitud', $sol->id_solicitud)->whereNull('consumido_en')->whereNull('revocado_en')->update(['revocado_en' => now()]);
            $token = Str::random(48);
            $vence = now()->addHours((int) $this->configuracion->valor('admision.correccion_horas', 72));
            if (! $this->configuracion->valor('admision.correccion_tras_cierre') && $conv->fin && $conv->fin->lt($vence)) {
                $vence = $conv->fin;
            }
            CorreccionIngreso::create(['id_solicitud' => $sol->id_solicitud, 'token_hash' => hash('sha256', $token), 'expira_en' => $vence]);
            DecisionIngreso::create(['id_solicitud' => $sol->id_solicitud, 'revision' => $sol->revision_actual, 'estado' => 'observada', 'motivo' => $motivo, 'id_usuario' => $actor, 'instante' => now()]);
            $sol->estado = 'observada';
            $sol->motivo_observacion = $motivo;
            $sol->version++;
            $sol->save();
            $entrega = $this->identidad->prepararEntrega($sol->id_usuario, 'observacion', $sol->email, [
                'folio' => $sol->folio, 'motivo' => $motivo, 'vence' => $vence->copy()->setTimezone($this->configuracion->zona())->format('Y-m-d H:i'),
            ], $token);
            $this->auditoria->registrar($actor, 'solicitud.observar', 'solicitud_ingreso', $sol->id_solicitud, $motivo, ['revision' => $sol->revision_actual]);

            return $sol;
        });
        if ($entrega) {
            EnviarEntregaAccesoJob::dispatch($entrega->id_entrega)->afterCommit();
        }

        return $sol;
    }

    /**
     * Acepta: bloquea solicitud e identidad, asigna carnet, vincula/crea cuenta y crea expediente. Reintento equivalente devuelve el mismo expediente.
     */
    public function aceptar(SolicitudIngreso $sol, int $version, string $fechaIngreso, string $actor): Expediente
    {
        $entrega = null;
        $exp = DB::transaction(function () use ($sol, $version, $fechaIngreso, $actor, &$entrega) {
            $sol = SolicitudIngreso::query()->lockForUpdate()->findOrFail($sol->id_solicitud);
            if ($sol->estado === 'aceptada') {
                return Expediente::query()->where('id_solicitud', $sol->id_solicitud)->firstOrFail();
            }
            $this->catalogo->exigirVersion($sol, $version);
            if ($sol->estado !== 'recibida') {
                throw DominioException::conflicto('TRANSICION_INVALIDA', 'Solo se acepta una solicitud recibida.');
            }
            $conv = ConvocatoriaIngreso::query()->findOrFail($sol->id_convocatoria);
            $rev = SolicitudIngresoRevision::query()->where('id_solicitud', $sol->id_solicitud)->where('revision', $sol->revision_actual)->firstOrFail();

            // Requisitos exigibles a la aceptación (p. ej. título de bachiller pendiente).
            [$aplicables] = $this->admision->evaluarRequisitos($conv, $rev->datos['formulario'] ?? [], (int) $sol->version_requisitos);
            $entregados = SolicitudAnexo::query()->where('id_revision', $rev->id_revision)->pluck('codigo_requisito')->all();
            $faltan = $aplicables->where('tipo', 'anexo')->where('obligatorio', true)->reject(fn ($r) => in_array($r->codigo, $entregados, true))->pluck('nombre')->values()->all();
            if ($faltan) {
                throw DominioException::conflicto('REQUISITOS_PENDIENTES', 'Faltan requisitos para aceptar.', ['requisitos' => $faltan]);
            }
            if ($conv->requiere_pago) {
                $orden = OrdenPagoIngreso::query()->where('id_usuario', $sol->id_usuario)->where('id_convocatoria', $conv->id_convocatoria)->first();
                if (! $orden || $orden->estado !== 'validado') {
                    throw DominioException::conflicto('PAGO_NO_VALIDADO', 'El pago de la solicitud no está validado.');
                }
            }
            $pensum = $this->pensums->aplicable($sol->id_carrera, $fechaIngreso);
            if (! $pensum) {
                throw DominioException::conflicto('PENSUM_NO_APLICABLE', 'No hay pensum publicado aplicable a la fecha de ingreso.');
            }

            // Identidad: la cuenta aspirante titular de la solicitud. Bloquea la cuenta.
            $cuenta = AuthUsuario::query()->lockForUpdate()->findOrFail($sol->id_usuario);
            $estudiante = Estudiante::query()->where('id_usuario', $cuenta->id_usuario)->lockForUpdate()->first();
            if (! $estudiante && $sol->documento_numero) {
                $otro = Estudiante::query()->where('documento_tipo', $sol->documento_tipo)->where('documento_numero', $sol->documento_numero)->first();
                if ($otro) {
                    throw DominioException::conflicto('IDENTIDAD_AMBIGUA', 'El documento coincide con otro estudiante registrado. Requiere revisión autorizada.');
                }
            }
            $tipoEntrega = 'carnet_asignado';
            if (! $estudiante) {
                $carnet = $this->identidad->reservarCarnet($sol->primer_apellido, $sol->segundo_apellido);
                $f = $rev->datos['formulario'] ?? [];
                $estudiante = Estudiante::create([
                    'id_usuario' => $cuenta->id_usuario,
                    'carnet' => $carnet['carnet'],
                    'carnet_prefijo' => $carnet['prefijo'],
                    'carnet_anio' => $carnet['anio'],
                    'carnet_correlativo' => $carnet['correlativo'],
                    'carnet_regla' => $carnet['regla'],
                    'nombres' => $sol->nombres,
                    'primer_apellido' => $sol->primer_apellido,
                    'segundo_apellido' => $sol->segundo_apellido,
                    'email' => $cuenta->email,
                    'telefono' => $f['telefono_celular'] ?? null,
                    'direccion' => $f['direccion'] ?? null,
                    'contacto_emergencia' => $f['contacto_emergencia'] ?? null,
                    'documento_tipo' => $sol->documento_tipo,
                    'documento_numero' => $sol->documento_numero,
                    'fecha_nacimiento' => $sol->fecha_nacimiento,
                ]);
                // La cuenta aspirante adopta el carnet como username y conserva correo y contraseña.
                $this->identidad->reservarIdentificador($carnet['carnet'], $cuenta->id_usuario);
                DB::table('auth_identificador')->where('clave', IdentidadAccesoService::normalizarClave($cuenta->username))
                    ->where('id_usuario', $cuenta->id_usuario)->where('clave', '!=', IdentidadAccesoService::normalizarClave($cuenta->email))->delete();
                $cuenta->username = IdentidadAccesoService::normalizarClave($carnet['carnet']);
                $cuenta->save();
            }
            if (Expediente::query()->where('id_estudiante', $estudiante->id_estudiante)->where('id_carrera', $sol->id_carrera)->exists()) {
                throw DominioException::conflicto('EXPEDIENTE_EXISTENTE', 'El estudiante ya tiene expediente en esta carrera.');
            }
            $this->catalogo->asegurarPerfil($cuenta->id_usuario, 'academico_estudiante', $actor);

            $exp = Expediente::create([
                'id_estudiante' => $estudiante->id_estudiante,
                'id_carrera' => $sol->id_carrera,
                'id_pensum' => $pensum->id_pensum,
                'id_solicitud' => $sol->id_solicitud,
                'fecha_ingreso' => $fechaIngreso,
                'estado' => ExpedienteEstados::ACTIVO,
            ]);
            ExpedienteEvento::create(['id_expediente' => $exp->id_expediente, 'tipo' => 'admision', 'estado_nuevo' => ExpedienteEstados::ACTIVO, 'motivo' => 'Admisión '.$sol->folio, 'id_usuario' => $actor, 'instante' => now()]);
            DecisionIngreso::create(['id_solicitud' => $sol->id_solicitud, 'revision' => $sol->revision_actual, 'estado' => 'aceptada', 'id_usuario' => $actor, 'id_expediente' => $exp->id_expediente, 'instante' => now()]);
            $sol->estado = 'aceptada';
            $sol->version++;
            $sol->save();
            $entrega = $this->identidad->prepararEntrega($cuenta->id_usuario, $tipoEntrega, $cuenta->email, ['carnet' => $estudiante->carnet], null, $exp->id_expediente);
            $this->auditoria->registrar($actor, 'solicitud.aceptar', 'solicitud_ingreso', $sol->id_solicitud, null, ['id_expediente' => $exp->id_expediente, 'id_pensum' => $pensum->id_pensum]);
            $this->permisos->invalidarUsuario($cuenta->id_usuario);

            return $exp;
        });
        if ($entrega) {
            EnviarEntregaAccesoJob::dispatch($entrega->id_entrega)->afterCommit();
        }

        return $exp;
    }

    /**
     * Admisión administrativa con cuenta nueva (D08): carnet como username y contraseña temporal por correo.
     *
     * @param  array{nombres:string, primer_apellido:string, segundo_apellido?:?string, email:string, id_carrera:int, fecha_ingreso:string}  $d
     */
    public function admisionDirecta(array $d, string $actor): Expediente
    {
        $entrega = null;
        $exp = DB::transaction(function () use ($d, $actor, &$entrega) {
            $email = IdentidadAccesoService::normalizarClave($d['email']);
            if (! $this->identidad->identificadorDisponible($email)) {
                throw DominioException::conflicto('IDENTIFICADOR_OCUPADO', 'El correo ya pertenece a una cuenta. Use la admisión por solicitud.');
            }
            $pensum = $this->pensums->aplicable((int) $d['id_carrera'], $d['fecha_ingreso']);
            if (! $pensum) {
                throw DominioException::conflicto('PENSUM_NO_APLICABLE', 'No hay pensum publicado aplicable a la fecha de ingreso.');
            }
            $carnet = $this->identidad->reservarCarnet($d['primer_apellido'], $d['segundo_apellido'] ?? null);
            [$cuenta, $temporal] = $this->identidad->crearCuentaTemporal($carnet['carnet'], $email, $actor);
            $this->catalogo->asegurarPerfil($cuenta->id_usuario, 'academico_estudiante', $actor);
            $est = Estudiante::create([
                'id_usuario' => $cuenta->id_usuario, 'carnet' => $carnet['carnet'], 'carnet_prefijo' => $carnet['prefijo'], 'carnet_anio' => $carnet['anio'],
                'carnet_correlativo' => $carnet['correlativo'], 'carnet_regla' => $carnet['regla'], 'nombres' => $d['nombres'],
                'primer_apellido' => $d['primer_apellido'], 'segundo_apellido' => $d['segundo_apellido'] ?? null, 'email' => $email,
            ]);
            $exp = Expediente::create(['id_estudiante' => $est->id_estudiante, 'id_carrera' => $d['id_carrera'], 'id_pensum' => $pensum->id_pensum, 'fecha_ingreso' => $d['fecha_ingreso'], 'estado' => ExpedienteEstados::ACTIVO]);
            ExpedienteEvento::create(['id_expediente' => $exp->id_expediente, 'tipo' => 'admision', 'estado_nuevo' => ExpedienteEstados::ACTIVO, 'motivo' => 'Admisión administrativa', 'id_usuario' => $actor, 'instante' => now()]);
            $entrega = $this->identidad->prepararEntrega($cuenta->id_usuario, 'credenciales_nuevas', $email, [
                'carnet' => $carnet['carnet'], 'vence' => $cuenta->password_temporal_expira_en->setTimezone($this->configuracion->zona())->format('Y-m-d H:i'),
            ], $temporal, $exp->id_expediente);
            $this->auditoria->registrar($actor, 'expediente.admision_directa', 'expediente', $exp->id_expediente);

            return $exp;
        });
        EnviarEntregaAccesoJob::dispatch($entrega->id_entrega)->afterCommit();

        return $exp;
    }

    public function validarFechaIngreso(?string $fecha): string
    {
        if (! $fecha || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw ValidationException::withMessages(['fecha_ingreso' => 'La fecha de ingreso es obligatoria (Y-m-d).']);
        }

        return $fecha;
    }
}
