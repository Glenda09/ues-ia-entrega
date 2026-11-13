<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `nota_evaluacion`.
 */
class NotaEvaluacion extends Model
{
    protected $table = 'nota_evaluacion';

    protected $primaryKey = 'id_nota';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['version' => 1, 'condicion' => 'nota'];

    protected function casts(): array
    {
        return [
        ];
    }
}
