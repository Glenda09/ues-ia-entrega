<?php

namespace App\Services\Academico;

use App\Models\Academico\ConfiguracionAcademica;
use App\Models\Academico\ConfiguracionAcademicaVersion;
use App\Models\Academico\EscalaCalificacion;
use App\Support\Academico\DominioException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Configuración general académica versionada (BASE-02).
 *
 * Una fila vigente con versión de edición y revisiones inmutables. Los módulos leen
 * parámetros mediante `valor()`/`grupo()`. La caché compartida se invalida por versión.
 */
class ConfiguracionService
{
    public const GRUPOS = ['calendario', 'aplicacion', 'inscripcion', 'calificaciones', 'admision', 'credenciales'];

    public const REDONDEOS = ['half_up', 'half_even', 'down'];

    public const AUSENCIAS = ['bloquear', 'minimo'];

    private const CACHE = 'academico:configuracion:vigente';

    /**
     * Valores iniciales. Los parámetros institucionales sin dato quedan en null
     * y se exigen al publicar el recurso consumidor (CONFIGURACION_INCOMPLETA).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'calendario' => [
                'zona' => 'America/El_Salvador',
                'ciclo_inicio' => null,
                'ciclo_fin' => null,
                'inscripcion_inicio' => null,
                'inscripcion_fin' => null,
                'notas_inicio' => null,
                'notas_fin' => null,
                'gracia_horas_inscripcion' => 0,
                'gracia_horas_notas' => 0,
            ],
            'aplicacion' => [
                'permisos_gracia_inscripcion' => [],
                'permisos_gracia_notas' => [],
                'permitir_inscripcion_antes_clases' => true,
                'permitir_ciclos_solapados' => false,
            ],
            'inscripcion' => [
                'cupo_default' => 30,
                'carga_maxima_creditos' => null,
                'validar_choque_horario' => true,
                'permitir_repetir_reprobadas' => true,
                'correquisitos_activos' => false,
                'equivalencias_activas' => false,
            ],
            'calificaciones' => [
                'id_escala_vigente' => null,
            ],
            'admision' => [
                'mayoria_edad' => 18,
                'correccion_horas' => 72,
                'correccion_tras_cierre' => false,
                'max_bytes_archivo' => 2097152,
                'max_bytes_peticion' => 10485760,
                'monto_pago_usd' => '10.00',
            ],
            'credenciales' => [
                'password_temporal_horas' => 72,
                'remitente_nombre' => 'Administración Académica',
                'contacto_email' => null,
                'contacto_telefono' => null,
                'contacto_url' => null,
            ],
        ];
    }

    public function vigente(): ConfiguracionAcademica
    {
        $cache = Cache::get(self::CACHE);
        if (is_array($cache)) {
            return (new ConfiguracionAcademica)->newFromBuilder($cache);
        }
        $fila = ConfiguracionAcademica::query()->where('ambito', 'global')->first() ?? $this->crearInicial();
        Cache::put(self::CACHE, $fila->getAttributes(), 300);

        return $fila;
    }

    /** @return array<string, array<string, mixed>> */
    public function valores(): array
    {
        return array_replace_recursive(self::defaults(), $this->vigente()->valores ?? []);
    }

    /** @return array<string, mixed> */
    public function grupo(string $grupo): array
    {
        return $this->valores()[$grupo] ?? [];
    }

    public function valor(string $ruta, mixed $defecto = null): mixed
    {
        return data_get($this->valores(), $ruta, $defecto);
    }

    public function zona(): string
    {
        return (string) $this->valor('calendario.zona', 'America/El_Salvador');
    }

    public function revisionActual(): int
    {
        return (int) $this->vigente()->version;
    }

    /**
     * Actualiza grupos completos o parciales con versión esperada y motivo.
     *
     * @param  array<string, array<string, mixed>>  $grupos
     */
    public function actualizar(array $grupos, int $versionEsperada, string $motivo, string $idUsuario): ConfiguracionAcademica
    {
        $desconocidos = array_diff(array_keys($grupos), self::GRUPOS);
        if ($desconocidos) {
            throw ValidationException::withMessages(['grupos' => 'Grupos desconocidos: '.implode(', ', $desconocidos)]);
        }

        $fila = DB::transaction(function () use ($grupos, $versionEsperada, $motivo, $idUsuario) {
            $this->vigente();
            $fila = ConfiguracionAcademica::query()->where('ambito', 'global')->lockForUpdate()->firstOrFail();
            if ((int) $fila->version !== $versionEsperada) {
                throw DominioException::conflicto('VERSION_CONFLICTO', 'La configuración fue modificada por otra persona. Recarga los valores.', ['version_actual' => (int) $fila->version]);
            }
            $actuales = array_replace_recursive(self::defaults(), $fila->valores ?? []);
            foreach ($grupos as $grupo => $valores) {
                $desconocidas = array_diff(array_keys((array) $valores), array_keys(self::defaults()[$grupo]));
                if ($desconocidas) {
                    throw ValidationException::withMessages(["grupos.$grupo" => 'Campos desconocidos: '.implode(', ', $desconocidas)]);
                }
                $actuales[$grupo] = array_replace($actuales[$grupo], (array) $valores);
            }
            $this->validar($actuales);

            $fila->valores = $actuales;
            $fila->version = $fila->version + 1;
            $fila->save();

            ConfiguracionAcademicaVersion::create([
                'id_configuracion' => $fila->id_configuracion,
                'revision' => $fila->version,
                'valores' => $actuales,
                'motivo' => $motivo,
                'id_usuario' => $idUsuario,
                'instante' => now('UTC'),
            ]);

            app(AuditoriaService::class)->registrar($idUsuario, 'configuracion.actualizar', 'configuracion_academica', $fila->id_configuracion, $motivo, ['revision' => $fila->version, 'grupos' => array_keys($grupos)]);

            return $fila;
        });

        Cache::forget(self::CACHE);

        return $fila;
    }

    /**
     * Validación de coherencia de todos los grupos. Lanza 422 sin cambios parciales.
     *
     * @param  array<string, array<string, mixed>>  $v
     */
    public function validar(array $v): void
    {
        $validador = Validator::make($v, [
            'calendario.zona' => 'required|string|timezone:all',
            'calendario.ciclo_inicio' => 'nullable|date_format:Y-m-d',
            'calendario.ciclo_fin' => 'nullable|date_format:Y-m-d|after:calendario.ciclo_inicio',
            'calendario.inscripcion_inicio' => 'nullable|date_format:Y-m-d H:i',
            'calendario.inscripcion_fin' => 'nullable|date_format:Y-m-d H:i|after:calendario.inscripcion_inicio',
            'calendario.notas_inicio' => 'nullable|date_format:Y-m-d H:i',
            'calendario.notas_fin' => 'nullable|date_format:Y-m-d H:i|after:calendario.notas_inicio',
            'calendario.gracia_horas_inscripcion' => 'required|integer|min:0|max:720',
            'calendario.gracia_horas_notas' => 'required|integer|min:0|max:720',
            'aplicacion.permisos_gracia_inscripcion' => 'present|array',
            'aplicacion.permisos_gracia_inscripcion.*' => 'string|max:100',
            'aplicacion.permisos_gracia_notas' => 'present|array',
            'aplicacion.permisos_gracia_notas.*' => 'string|max:100',
            'aplicacion.permitir_inscripcion_antes_clases' => 'required|boolean',
            'aplicacion.permitir_ciclos_solapados' => 'required|boolean',
            'inscripcion.cupo_default' => 'required|integer|min:1|max:1000',
            'inscripcion.carga_maxima_creditos' => 'nullable|numeric|gt:0',
            'inscripcion.validar_choque_horario' => 'required|boolean',
            'inscripcion.permitir_repetir_reprobadas' => 'required|boolean',
            'inscripcion.correquisitos_activos' => 'required|boolean',
            'inscripcion.equivalencias_activas' => 'required|boolean',
            'calificaciones.id_escala_vigente' => 'nullable|integer|exists:escala_calificacion,id_escala',
            'admision.mayoria_edad' => 'required|integer|min:1|max:30',
            'admision.correccion_horas' => 'required|integer|min:1|max:2160',
            'admision.correccion_tras_cierre' => 'required|boolean',
            'admision.max_bytes_archivo' => 'required|integer|min:1024',
            'admision.max_bytes_peticion' => 'required|integer|min:1024|gte:admision.max_bytes_archivo',
            'admision.monto_pago_usd' => 'required|numeric|min:0',
            'credenciales.password_temporal_horas' => 'required|integer|min:1|max:2160',
            'credenciales.remitente_nombre' => 'required|string|max:120',
            'credenciales.contacto_email' => 'nullable|email|max:200',
            'credenciales.contacto_telefono' => 'nullable|string|max:40',
            'credenciales.contacto_url' => 'nullable|url|max:300',
        ], [], []);

        $validador->after(function ($val) use ($v) {
            $c = $v['calendario'];
            if ($c['ciclo_inicio'] && ! $c['ciclo_fin']) {
                $val->errors()->add('calendario.ciclo_fin', 'El fin del ciclo es obligatorio si se define el inicio.');
            }
            foreach (['inscripcion', 'notas'] as $tipo) {
                if (($c["{$tipo}_inicio"] ?? null) xor ($c["{$tipo}_fin"] ?? null)) {
                    $val->errors()->add("calendario.{$tipo}_fin", 'La ventana requiere inicio y fin.');
                }
            }
            if ($c['notas_inicio'] && $c['ciclo_inicio'] && $c['notas_inicio'] < $c['ciclo_inicio'].' 00:00') {
                $val->errors()->add('calendario.notas_inicio', 'La ventana de notas no puede iniciar antes del ciclo.');
            }
            if ($c['inscripcion_inicio'] && $c['ciclo_inicio'] && ! $v['aplicacion']['permitir_inscripcion_antes_clases'] && $c['inscripcion_inicio'] < $c['ciclo_inicio'].' 00:00') {
                $val->errors()->add('calendario.inscripcion_inicio', 'La inscripción no puede iniciar antes del ciclo según la configuración.');
            }
        });

        if ($validador->fails()) {
            throw new ValidationException($validador);
        }
    }

    /**
     * Valores sugeridos para un ciclo nuevo en borrador (instantes UTC derivados de la zona).
     *
     * @return array<string, mixed>
     */
    public function defaultsCiclo(): array
    {
        $c = $this->grupo('calendario');
        $zona = $c['zona'];
        $aUtc = fn (?string $local, string $hora = '') => $local ? CarbonImmutable::parse($local.$hora, $zona)->utc() : null;
        $gracia = function (?CarbonImmutable $fin, int $horas) {
            return $fin && $horas > 0 ? $fin->addHours($horas) : null;
        };
        $insFin = $aUtc($c['inscripcion_fin']);
        $notFin = $aUtc($c['notas_fin']);

        return [
            'zona' => $zona,
            'inicio' => $aUtc($c['ciclo_inicio'], ' 00:00'),
            'fin' => $aUtc($c['ciclo_fin'], ' 00:00'),
            'ventanas' => [
                'inscripcion' => ['inicio' => $aUtc($c['inscripcion_inicio']), 'fin_regular' => $insFin, 'fin_gracia' => $gracia($insFin, (int) $c['gracia_horas_inscripcion'])],
                'notas' => ['inicio' => $aUtc($c['notas_inicio']), 'fin_regular' => $notFin, 'fin_gracia' => $gracia($notFin, (int) $c['gracia_horas_notas'])],
            ],
        ];
    }

    /**
     * Crea una nueva versión de escala de calificación validada (D05).
     *
     * @param  array<string, mixed>  $d
     */
    public function crearEscala(array $d, string $idUsuario): EscalaCalificacion
    {
        $this->validarEscala($d);

        return DB::transaction(function () use ($d, $idUsuario) {
            // Serializa versiones del mismo código bloqueando la configuración global (FOR UPDATE no admite agregados en PostgreSQL).
            ConfiguracionAcademica::query()->where('ambito', 'global')->lockForUpdate()->first();
            $siguiente = (int) EscalaCalificacion::query()->where('codigo', $d['codigo'])->max('version_politica') + 1;
            $escala = EscalaCalificacion::create([
                'codigo' => $d['codigo'],
                'version_politica' => $siguiente,
                'nombre' => $d['nombre'],
                'minimo' => $d['minimo'],
                'maximo' => $d['maximo'],
                'umbral_aprobacion' => $d['umbral_aprobacion'],
                'precision' => $d['precision'],
                'redondeo' => $d['redondeo'],
                'ponderacion_total' => $d['ponderacion_total'],
                'ausencia' => $d['ausencia'],
                'creado_por' => $idUsuario,
            ]);
            app(AuditoriaService::class)->registrar($idUsuario, 'escala.crear', 'escala_calificacion', $escala->id_escala, null, ['codigo' => $escala->codigo, 'version_politica' => $siguiente]);

            return $escala;
        });
    }

    /** @param array<string, mixed> $d */
    public function validarEscala(array $d): void
    {
        $validador = Validator::make($d, [
            'codigo' => 'required|string|max:50',
            'nombre' => 'required|string|max:200',
            'minimo' => 'required|numeric',
            'maximo' => 'required|numeric|gt:minimo',
            'umbral_aprobacion' => 'required|numeric|gte:minimo|lte:maximo',
            'precision' => 'required|integer|min:0|max:4',
            'redondeo' => 'required|in:'.implode(',', self::REDONDEOS),
            'ponderacion_total' => 'required|numeric|gt:0',
            'ausencia' => 'required|in:'.implode(',', self::AUSENCIAS),
        ]);
        if ($validador->fails()) {
            throw new ValidationException($validador);
        }
    }

    public function escalaVigente(): ?EscalaCalificacion
    {
        $id = $this->valor('calificaciones.id_escala_vigente');

        return $id ? EscalaCalificacion::find($id) : null;
    }

    /**
     * Catálogos de opciones para validación coherente en la UI.
     *
     * @return array<string, mixed>
     */
    public function catalogos(): array
    {
        return [
            'zonas' => \DateTimeZone::listIdentifiers(),
            'redondeos' => self::REDONDEOS,
            'ausencias' => self::AUSENCIAS,
            'estados_estudiante' => ['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo', 'EGRESADO' => 'Egresado', 'RETIRADO' => 'Retirado'],
            'matriz_estados' => ExpedienteEstados::MATRIZ,
            'formato_carnet' => 'Inicial primer apellido + inicial segundo apellido (X si falta) + año + correlativo (mín. 2 dígitos). Ej.: SH202601',
            'permisos_operativos' => ['academico_mi_inscripcion_gestionar', 'academico_mi_nota_gestionar', 'academico_acta_publicar'],
        ];
    }

    private function crearInicial(): ConfiguracionAcademica
    {
        return DB::transaction(function () {
            $fila = ConfiguracionAcademica::query()->firstOrCreate(['ambito' => 'global'], ['version' => 1, 'valores' => self::defaults()]);
            ConfiguracionAcademicaVersion::query()->firstOrCreate(
                ['id_configuracion' => $fila->id_configuracion, 'revision' => 1],
                ['valores' => $fila->valores, 'motivo' => 'Configuración inicial', 'instante' => now('UTC')]
            );

            return $fila;
        });
    }
}
