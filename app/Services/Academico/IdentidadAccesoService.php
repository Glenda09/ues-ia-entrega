<?php

namespace App\Services\Academico;

use App\Jobs\Academico\EnviarEntregaAccesoJob;
use App\Models\Academico\CarnetCorrelativo;
use App\Models\Academico\EntregaAcceso;
use App\Models\Auth\AuthUsuario;
use App\Support\Academico\Codigo;
use App\Support\Academico\DominioException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * D01/D08 (EXP-02 y EST-01): carnet transaccional, reserva única de identificadores
 * de acceso y entrega de credenciales después del commit.
 */
class IdentidadAccesoService
{
    public function __construct(private ConfiguracionService $configuracion) {}

    public static function normalizarClave(string $clave): string
    {
        return mb_strtolower(trim($clave));
    }

    /**
     * Prefijo de carnet: inicial del primer apellido + inicial del segundo (X si falta).
     *
     * @return array{0:string, 1:string} [prefijo, regla aplicada]
     */
    public static function prefijo(string $primerApellido, ?string $segundoApellido): array
    {
        $regla = trim((string) $segundoApellido) === '' ? 'segundo_apellido_ausente_x' : 'iniciales_apellidos';

        return [Codigo::inicial($primerApellido).Codigo::inicial($segundoApellido), $regla];
    }

    /**
     * Reserva el siguiente carnet disponible dentro de la transacción en curso.
     * Bloquea el contador (prefijo, año) y consume correlativos que colisionen con identificadores previos.
     *
     * @return array{carnet:string, prefijo:string, anio:int, correlativo:int, regla:string}
     */
    public function reservarCarnet(string $primerApellido, ?string $segundoApellido, ?CarbonImmutable $instante = null): array
    {
        [$prefijo, $regla] = self::prefijo($primerApellido, $segundoApellido);
        $anio = (int) ($instante ?? CarbonImmutable::now('UTC'))->setTimezone($this->configuracion->zona())->format('Y');

        DB::table('carnet_correlativo')->insertOrIgnore(['prefijo' => $prefijo, 'anio' => $anio, 'ultimo' => 0]);
        $contador = CarnetCorrelativo::query()->where('prefijo', $prefijo)->where('anio', $anio)->lockForUpdate()->firstOrFail();

        $n = (int) $contador->ultimo;
        do {
            $n++;
            $carnet = $prefijo.$anio.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $ocupado = DB::table('auth_identificador')->where('clave', self::normalizarClave($carnet))->exists()
                || DB::table('estudiante')->where('carnet', $carnet)->exists();
        } while ($ocupado);

        $contador->ultimo = $n;
        $contador->save();

        return ['carnet' => $carnet, 'prefijo' => $prefijo, 'anio' => $anio, 'correlativo' => $n, 'regla' => $regla];
    }

    /**
     * Reserva atómicamente un identificador de acceso. Cuentas distintas no pueden reclamarlo.
     */
    public function reservarIdentificador(string $clave, string $idUsuario): void
    {
        $clave = self::normalizarClave($clave);
        $insertado = DB::table('auth_identificador')->insertOrIgnore(['clave' => $clave, 'id_usuario' => $idUsuario, 'created_at' => now(), 'updated_at' => now()]);
        if (! $insertado) {
            $dueno = DB::table('auth_identificador')->where('clave', $clave)->value('id_usuario');
            if ($dueno !== $idUsuario) {
                throw DominioException::conflicto('IDENTIFICADOR_OCUPADO', 'El identificador de acceso ya pertenece a otra cuenta.');
            }
        }
    }

    public function identificadorDisponible(string $clave, ?string $idUsuario = null): bool
    {
        $dueno = DB::table('auth_identificador')->where('clave', self::normalizarClave($clave))->value('id_usuario');

        return $dueno === null || $dueno === $idUsuario;
    }

    /**
     * Crea una cuenta nueva con contraseña temporal y cambio inicial obligatorio.
     * Debe ejecutarse dentro de la transacción de admisión.
     *
     * @return array{0: AuthUsuario, 1: string} [cuenta, contraseña temporal en claro para la entrega cifrada]
     */
    public function crearCuentaTemporal(string $username, string $email, string $creadoPor): array
    {
        $temporal = $this->generarTemporal();
        $u = new AuthUsuario([
            'username' => self::normalizarClave($username),
            'email' => self::normalizarClave($email),
            'password' => $temporal,
            'fecha_verificacion' => now(),
            'id_estado' => 'ACTIVO',
            'creado_por' => $creadoPor,
        ]);
        $u->password_cambio_requerido = true;
        $u->password_temporal_expira_en = now()->addHours((int) $this->configuracion->valor('credenciales.password_temporal_horas', 72));
        $u->save();
        $this->reservarIdentificador($u->username, $u->id_usuario);
        $this->reservarIdentificador($u->email, $u->id_usuario);

        return [$u, $temporal];
    }

    /**
     * Registra una entrega pendiente. El secreto, si existe, se guarda cifrado con TTL.
     */
    public function prepararEntrega(string $idUsuario, string $tipo, string $destinatario, array $datos = [], ?string $secreto = null, ?int $idExpediente = null): EntregaAcceso
    {
        return EntregaAcceso::create([
            'id_usuario' => $idUsuario,
            'id_expediente' => $idExpediente,
            'tipo' => $tipo,
            'destinatario' => $destinatario,
            'estado' => 'pendiente',
            'secreto_cifrado' => $secreto !== null ? Crypt::encryptString($secreto) : null,
            'secreto_expira_en' => $secreto !== null ? now()->addHours((int) $this->configuracion->valor('credenciales.password_temporal_horas', 72)) : null,
            'datos' => $datos,
        ]);
    }

    /**
     * Reenvío controlado: genera una nueva contraseña temporal e invalida la anterior.
     */
    public function reenviarCredenciales(AuthUsuario $u): EntregaAcceso
    {
        if (! $u->password_cambio_requerido) {
            throw DominioException::conflicto('REENVIO_NO_APLICA', 'La cuenta ya completó el cambio de contraseña inicial.');
        }
        $entrega = DB::transaction(function () use ($u) {
            $temporal = $this->generarTemporal();
            $u->password = Hash::make($temporal);
            $u->password_temporal_expira_en = now()->addHours((int) $this->configuracion->valor('credenciales.password_temporal_horas', 72));
            $u->save();

            return $this->prepararEntrega($u->id_usuario, 'credenciales_nuevas', $u->email, ['carnet' => strtoupper($u->username)], $temporal);
        });
        EnviarEntregaAccesoJob::dispatch($entrega->id_entrega)->afterCommit();

        return $entrega;
    }

    private function generarTemporal(): string
    {
        return Str::password(12, true, true, false);
    }
}
