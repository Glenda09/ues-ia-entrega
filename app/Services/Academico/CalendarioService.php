<?php

namespace App\Services\Academico;

use App\Models\Academico\Carrera;
use App\Models\Academico\CicloAjuste;
use App\Models\Academico\CicloCarrera;
use App\Models\Academico\Grupo;
use App\Models\Academico\Inscripcion;
use App\Models\Academico\VentanaCiclo;
use App\Support\Academico\DominioException;
use App\Support\Academico\Reloj;
use App\Support\Academico\Ventana;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CAR-01: ciclos, ventanas y lectura única de plazos publicados.
 */
class CalendarioService
{
    public function __construct(
        private ConfiguracionService $configuracion,
        private AuditoriaService $auditoria,
        private CatalogoService $catalogo,
        private Reloj $reloj,
    ) {}

    /**
     * Crea un ciclo en borrador partiendo del calendario general (BASE-02).
     */
    public function crear(Carrera $carrera, array $d, string $actor): CicloCarrera
    {
        if ($carrera->estado !== 'activa') {
            throw DominioException::conflicto('CARRERA_ARCHIVADA', 'La carrera está archivada.');
        }

        return DB::transaction(function () use ($carrera, $d, $actor) {
            if (CicloCarrera::query()->where('id_carrera', $carrera->id_carrera)->where('codigo', $d['codigo'])->exists()) {
                throw ValidationException::withMessages(['codigo' => 'El código de ciclo ya existe en la carrera.']);
            }
            $def = $this->configuracion->defaultsCiclo();
            $ciclo = CicloCarrera::create([
                'id_carrera' => $carrera->id_carrera,
                'codigo' => $d['codigo'],
                'nombre' => $d['nombre'],
                'zona' => $def['zona'],
                'inicio' => $def['inicio'],
                'fin' => $def['fin'],
                'estado' => 'borrador',
                'creado_por' => $actor,
            ]);
            foreach (['inscripcion', 'notas'] as $tipo) {
                VentanaCiclo::create(['id_ciclo_carrera' => $ciclo->id_ciclo_carrera, 'tipo' => $tipo] + $def['ventanas'][$tipo]);
            }
            if (! empty($d['calendario'])) {
                $this->aplicarCalendario($ciclo, $d['calendario']);
            }
            $this->auditoria->registrar($actor, 'ciclo.crear', 'ciclo_carrera', $ciclo->id_ciclo_carrera);

            return $ciclo->fresh();
        });
    }

    /**
     * Edita calendario (borrador) o ajusta plazos de un publicado con motivo y versión.
     *
     * @param  array{version:int, nombre?:string, motivo?:string, calendario?:array}  $d
     */
    public function actualizar(CicloCarrera $ciclo, array $d, string $actor): CicloCarrera
    {
        return DB::transaction(function () use ($ciclo, $d, $actor) {
            $ciclo = CicloCarrera::query()->lockForUpdate()->findOrFail($ciclo->id_ciclo_carrera);
            $this->catalogo->exigirVersion($ciclo, (int) $d['version']);
            if ($ciclo->estado === 'cerrado') {
                throw DominioException::conflicto('CICLO_CERRADO', 'El ciclo está cerrado y no admite cambios.');
            }
            $anterior = $this->calendarioJson($ciclo);
            if ($ciclo->estado === 'publicado') {
                if (empty($d['motivo'])) {
                    throw ValidationException::withMessages(['motivo' => 'El ajuste de un ciclo publicado requiere motivo.']);
                }
                if (isset($d['nombre'])) {
                    $ciclo->nombre = $d['nombre'];
                }
            } elseif (isset($d['nombre'])) {
                $ciclo->nombre = $d['nombre'];
            }
            if (! empty($d['calendario'])) {
                $this->aplicarCalendario($ciclo, $d['calendario']);
                if ($ciclo->estado === 'publicado') {
                    $this->validarFechas($ciclo, true);
                }
            }
            $ciclo->version++;
            $ciclo->save();
            if ($ciclo->estado === 'publicado') {
                CicloAjuste::create([
                    'id_ciclo_carrera' => $ciclo->id_ciclo_carrera,
                    'version_resultante' => $ciclo->version,
                    'anterior' => $anterior,
                    'nuevo' => $this->calendarioJson($ciclo->fresh()),
                    'motivo' => $d['motivo'] ?? null,
                    'id_usuario' => $actor,
                    'instante' => now('UTC'),
                ]);
            }
            $this->auditoria->registrar($actor, 'ciclo.actualizar', 'ciclo_carrera', $ciclo->id_ciclo_carrera, $d['motivo'] ?? null, ['estado' => $ciclo->estado, 'version' => $ciclo->version]);

            return $ciclo->fresh();
        });
    }

    /**
     * Transición borrador → publicado → cerrado.
     */
    public function transicion(CicloCarrera $ciclo, string $estado, int $version, string $actor): CicloCarrera
    {
        return DB::transaction(function () use ($ciclo, $estado, $version, $actor) {
            $ciclo = CicloCarrera::query()->lockForUpdate()->findOrFail($ciclo->id_ciclo_carrera);
            if ($ciclo->estado === $estado) {
                return $ciclo;
            }
            $this->catalogo->exigirVersion($ciclo, $version);
            $permitidas = ['borrador' => ['publicado'], 'publicado' => ['cerrado']];
            if (! in_array($estado, $permitidas[$ciclo->estado] ?? [], true)) {
                throw DominioException::conflicto('TRANSICION_INVALIDA', "No se permite pasar de {$ciclo->estado} a {$estado}.");
            }
            if ($estado === 'publicado') {
                $this->validarPublicacion($ciclo);
                $ciclo->version_configuracion = $this->configuracion->revisionActual();
                $ciclo->configuracion_aplicada = [
                    'aplicacion' => $this->configuracion->grupo('aplicacion'),
                    'inscripcion' => $this->configuracion->grupo('inscripcion'),
                    'zona' => $ciclo->zona,
                    'id_escala' => $this->configuracion->valor('calificaciones.id_escala_vigente'),
                ];
            } else {
                $this->validarCierre($ciclo);
            }
            $ciclo->estado = $estado;
            $ciclo->version++;
            $ciclo->save();
            $this->auditoria->registrar($actor, 'ciclo.transicion', 'ciclo_carrera', $ciclo->id_ciclo_carrera, null, ['estado' => $estado]);

            return $ciclo;
        });
    }

    /**
     * Estado de una ventana del ciclo en el instante dado (lectura única de plazos).
     *
     * @return array{tipo:string, estado:string, inicio:?string, fin_regular:?string, fin_gracia:?string, fin_efectivo:?string}
     */
    public function estadoVentana(CicloCarrera $ciclo, string $tipo, ?CarbonImmutable $instante = null): array
    {
        $v = VentanaCiclo::query()->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)->where('tipo', $tipo)->first();
        $estado = Ventana::evaluar($v?->inicio, $v?->fin_regular, $v?->fin_gracia, $ciclo->estado === 'publicado', $instante ?? $this->reloj->ahora());

        return [
            'tipo' => $tipo,
            'estado' => $estado,
            'inicio' => $v?->inicio?->toIso8601String(),
            'fin_regular' => $v?->fin_regular?->toIso8601String(),
            'fin_gracia' => $v?->fin_gracia?->toIso8601String(),
            'fin_efectivo' => ($v?->fin_gracia ?? $v?->fin_regular)?->toIso8601String(),
        ];
    }

    /**
     * Comprueba que el actor pueda operar en la ventana; lanza `PERIODO_CERRADO` si no.
     *
     * @param  array<int, string>  $permisosActor
     */
    public function exigirVentana(CicloCarrera $ciclo, string $tipo, array $permisosActor): string
    {
        $estado = $this->estadoVentana($ciclo, $tipo)['estado'];
        $gracia = (array) data_get($ciclo->configuracion_aplicada, "aplicacion.permisos_gracia_{$tipo}", $this->configuracion->valor("aplicacion.permisos_gracia_{$tipo}", []));
        if (! Ventana::puedeOperar($estado, $permisosActor, $gracia)) {
            throw DominioException::conflicto('PERIODO_CERRADO', $tipo === 'notas' ? 'El periodo de registro de notas no está abierto.' : 'El periodo de inscripción no está abierto.', ['estado_ventana' => $estado]);
        }

        return $estado;
    }

    /**
     * @return array<string, mixed>
     */
    public function calendarioJson(CicloCarrera $ciclo): array
    {
        $ventanas = VentanaCiclo::query()->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)->get()->keyBy('tipo');
        $iso = fn ($v) => $v?->toIso8601String();

        return [
            'zona' => $ciclo->zona,
            'inicio' => $iso($ciclo->inicio),
            'fin' => $iso($ciclo->fin),
            'ventanas' => collect(['inscripcion', 'notas'])->mapWithKeys(fn ($t) => [$t => [
                'inicio' => $iso($ventanas[$t]->inicio ?? null),
                'fin_regular' => $iso($ventanas[$t]->fin_regular ?? null),
                'fin_gracia' => $iso($ventanas[$t]->fin_gracia ?? null),
            ]])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cicloJson(CicloCarrera $ciclo): array
    {
        return [
            'id_ciclo_carrera' => $ciclo->id_ciclo_carrera,
            'id_carrera' => $ciclo->id_carrera,
            'codigo' => $ciclo->codigo,
            'nombre' => $ciclo->nombre,
            'estado' => $ciclo->estado,
            'version' => (int) $ciclo->version,
            'version_configuracion' => $ciclo->version_configuracion,
            'calendario' => $this->calendarioJson($ciclo),
            'ventanas_estado' => [
                'inscripcion' => $this->estadoVentana($ciclo, 'inscripcion')['estado'],
                'notas' => $this->estadoVentana($ciclo, 'notas')['estado'],
            ],
            'ajustes' => CicloAjuste::query()->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)->orderBy('id_ciclo_ajuste')->get(['version_resultante', 'motivo', 'instante']),
        ];
    }

    /**
     * Aplica fechas locales (Y-m-d H:i en la zona del ciclo) al ciclo y sus ventanas.
     */
    private function aplicarCalendario(CicloCarrera $ciclo, array $cal): void
    {
        $zona = $ciclo->zona;
        $utc = fn ($v) => $v ? CarbonImmutable::parse($v, $zona)->utc() : null;
        foreach (['inicio', 'fin'] as $c) {
            if (array_key_exists($c, $cal)) {
                $ciclo->{$c} = $utc($cal[$c]);
            }
        }
        $ciclo->save();
        foreach (['inscripcion', 'notas'] as $tipo) {
            if (! isset($cal['ventanas'][$tipo])) {
                continue;
            }
            $v = VentanaCiclo::query()->firstOrNew(['id_ciclo_carrera' => $ciclo->id_ciclo_carrera, 'tipo' => $tipo]);
            foreach (['inicio', 'fin_regular', 'fin_gracia'] as $c) {
                if (array_key_exists($c, $cal['ventanas'][$tipo])) {
                    $v->{$c} = $utc($cal['ventanas'][$tipo][$c]);
                }
            }
            $v->save();
        }
    }

    private function validarPublicacion(CicloCarrera $ciclo): void
    {
        $faltantes = [];
        $carrera = Carrera::find($ciclo->id_carrera);
        if ($carrera->estado !== 'activa') {
            throw DominioException::conflicto('CARRERA_ARCHIVADA', 'La carrera está archivada.');
        }
        if (! DB::table('pensum')->where('id_carrera', $ciclo->id_carrera)->where('estado', 'publicado')->exists()) {
            throw DominioException::conflicto('PENSUM_NO_PUBLICADO', 'La carrera requiere un pensum publicado.');
        }
        if (! $this->configuracion->valor('calificaciones.id_escala_vigente')) {
            $faltantes[] = 'calificaciones.id_escala_vigente';
        }
        if ($faltantes) {
            throw DominioException::configuracionIncompleta($faltantes);
        }
        $this->validarFechas($ciclo, false);
        if (! $this->configuracion->valor('aplicacion.permitir_ciclos_solapados')) {
            $solapa = CicloCarrera::query()->where('id_carrera', $ciclo->id_carrera)->where('estado', 'publicado')
                ->where('id_ciclo_carrera', '!=', $ciclo->id_ciclo_carrera)
                ->where('inicio', '<', $ciclo->fin)->where('fin', '>', $ciclo->inicio)->exists();
            if ($solapa) {
                throw DominioException::conflicto('CICLOS_SOLAPADOS', 'La configuración no permite ciclos publicados solapados.');
            }
        }
    }

    /**
     * Valida inicio < fin regular <= fin gracia <= fin ciclo y relación con clases.
     */
    private function validarFechas(CicloCarrera $ciclo, bool $ajuste): void
    {
        $e = [];
        if (! $ciclo->inicio || ! $ciclo->fin || $ciclo->inicio->gte($ciclo->fin)) {
            $e['calendario.fin'][] = 'El ciclo requiere inicio anterior al fin.';
        }
        $antesClases = (bool) $this->configuracion->valor('aplicacion.permitir_inscripcion_antes_clases');
        foreach (VentanaCiclo::query()->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)->get() as $v) {
            $k = "calendario.ventanas.{$v->tipo}";
            if (! $v->inicio || ! $v->fin_regular) {
                $e[$k][] = 'La ventana requiere inicio y fin regular.';

                continue;
            }
            if (! $v->inicio->lt($v->fin_regular)) {
                $e[$k][] = 'El inicio debe ser anterior al fin regular.';
            }
            if ($v->fin_gracia && $v->fin_gracia->lt($v->fin_regular)) {
                $e[$k][] = 'El fin de gracia no puede ser anterior al fin regular.';
            }
            if ($ciclo->fin && ($v->fin_gracia ?? $v->fin_regular)->gt($ciclo->fin)) {
                $e[$k][] = 'La ventana debe terminar dentro del ciclo.';
            }
            if ($ciclo->inicio && $v->inicio->lt($ciclo->inicio) && ($v->tipo === 'notas' || ! $antesClases)) {
                $e[$k][] = 'La ventana no puede iniciar antes del ciclo.';
            }
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }
        if ($ajuste) {
            // Un ajuste no habilita cambios sobre grupos con actas publicadas: se valida en INS/DOC.
        }
    }

    private function validarCierre(CicloCarrera $ciclo): void
    {
        $notas = $this->estadoVentana($ciclo, 'notas');
        if (in_array($notas['estado'], [Ventana::REGULAR, Ventana::GRACIA, Ventana::ANTICIPADO], true)) {
            throw DominioException::conflicto('CIERRE_NO_PERMITIDO', 'El plazo de notas aún no termina.');
        }
        $gruposSinActa = Grupo::query()->whereHas('oferta', fn ($q) => $q->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera))
            ->whereIn('id_grupo', Inscripcion::query()->where('id_ciclo_carrera', $ciclo->id_ciclo_carrera)->where('estado', 'activa')->select('id_grupo'))
            ->whereDoesntHave('acta', fn ($q) => $q->where('estado', 'publicada'))
            ->count();
        if ($gruposSinActa > 0) {
            throw DominioException::conflicto('CIERRE_NO_PERMITIDO', 'Hay grupos con inscripciones activas sin acta publicada.', ['grupos_pendientes' => $gruposSinActa]);
        }
    }
}
