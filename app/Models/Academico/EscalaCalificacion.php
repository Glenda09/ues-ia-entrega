<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `escala_calificacion`.
 */
class EscalaCalificacion extends Model
{
    protected $table = 'escala_calificacion';

    protected $primaryKey = 'id_escala';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
        ];
    }
}
