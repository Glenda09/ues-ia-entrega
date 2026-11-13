<?php

namespace App\Services\Academico;

use App\Models\Academico\Estudiante;
use App\Models\Academico\Expediente;
use App\Models\Academico\ExpedienteEvento;
use App\Support\Academico\DominioException;
use Illuminate\Support\Facades\DB;

/**
 * EXP-03: datos del expediente y transiciones de estado según la matriz D06.
 */
class ExpedienteService
{
    public const CAMPOS_EDITABLES = ['nombre_uso', 'telefono', 'direccion', 'contacto_emergencia'];

    public function __construct(private AuditoriaService $auditoria, private CatalogoService $catalogo, private HistorialAcademicoService $historial) {}

    /**
     * Cambio de estado. Pedir el estado actual devuelve el vigente sin duplicar eventos.
     */
    public function transicion(Expediente $exp, string $estado, int $version, string $motivo, string $actor): Expediente
    {
        return DB::transaction(function () use ($exp, $estado, $version, $motivo, $actor) {
            $exp = Expediente::query()->lockForUpdate()->findOrFail($exp->id_expediente);
            if ($exp->estado === $estado) {
                return $exp;
            }
            $this->catalogo->exigirVersion($exp, $version);
            if (! ExpedienteEstados::permitida($exp->estado, $estado)) {
                throw DominioException::conflicto('TRANSICION_ESTUDIANTE_INVALIDA', sprintf('No se permite pasar de %s a %s.', ExpedienteEstados::ETIQUETAS[$exp->estado], ExpedienteEstados::ETIQUETAS[$estado]));
            }
            ExpedienteEvento::create([
                'id_expediente' => $exp->id_expediente, 'tipo' => 'estado', 'estado_anterior' => $exp->estado, 'estado_nuevo' => $estado,
                'motivo' => $motivo, 'id_usuario' => $actor, 'instante' => now(),
            ]);
            $anterior = $exp->estado;
            $exp->estado = $estado;
            $exp->version++;
            $exp->save();
            $this->auditoria->registrar($actor, 'expediente.estado', 'expediente', $exp->id_expediente, $motivo, ['de' => $anterior, 'a' => $estado]);

            return $exp;
        });
    }

    /**
     * Edición de campos permitidos del estudiante con versión del expediente.
     */
    public function actualizarDatos(Expediente $exp, array $d, string $actor): Expediente
    {
        return DB::transaction(function () use ($exp, $d, $actor) {
            $exp = Expediente::query()->lockForUpdate()->findOrFail($exp->id_expediente);
            $this->catalogo->exigirVersion($exp, (int) $d['version']);
            $est = Estudiante::query()->lockForUpdate()->findOrFail($exp->id_estudiante);
            $cambios = array_intersect_key($d, array_flip(self::CAMPOS_EDITABLES));
            $est->fill($cambios);
            if ($est->isDirty()) {
                $campos = array_keys($est->getDirty());
                $est->version++;
                $est->save();
                ExpedienteEvento::create(['id_expediente' => $exp->id_expediente, 'tipo' => 'datos', 'cambios' => ['campos' => $campos], 'id_usuario' => $actor, 'instante' => now()]);
                $exp->version++;
                $exp->save();
                $this->auditoria->registrar($actor, 'expediente.datos', 'expediente', $exp->id_expediente, null, ['campos' => $campos]);
            }

            return $exp;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function json(Expediente $exp, bool $detalle = false): array
    {
        $est = $exp->estudiante;
        $base = [
            'id_expediente' => $exp->id_expediente,
            'carnet' => $est->carnet,
            'nombre' => trim(($est->nombre_uso ?: $est->nombres).' '.$est->primer_apellido.' '.($est->segundo_apellido ?? '')),
            'id_carrera' => $exp->id_carrera,
            'carrera' => $exp->carrera?->nombre,
            'id_pensum' => $exp->id_pensum,
            'pensum' => $exp->pensum?->version_curricular,
            'estado' => $exp->estado,
            'estado_etiqueta' => ExpedienteEstados::ETIQUETAS[$exp->estado] ?? $exp->estado,
            'fecha_ingreso' => $exp->fecha_ingreso?->toDateString(),
            'version' => (int) $exp->version,
        ];
        if (! $detalle) {
            return $base;
        }

        return $base + [
            'estudiante' => $est->only(['nombres', 'primer_apellido', 'segundo_apellido', 'nombre_uso', 'email', 'telefono', 'direccion', 'contacto_emergencia', 'documento_tipo', 'documento_numero']),
            'transiciones_permitidas' => ExpedienteEstados::MATRIZ[$exp->estado] ?? [],
            'historial' => $this->historial->historial($exp),
        ];
    }
}
