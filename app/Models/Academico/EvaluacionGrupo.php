<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `evaluacion_grupo`.
 */
class EvaluacionGrupo extends Model
{
    protected $table = 'evaluacion_grupo';

    protected $primaryKey = 'id_evaluacion';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['version' => 1];

    protected function casts(): array
    {
        return [
        ];
    }

    public function notas()
    {
        return $this->hasMany(NotaEvaluacion::class, 'id_evaluacion');
    }
}
