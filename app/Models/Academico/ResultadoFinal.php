<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `resultado_final`.
 */
class ResultadoFinal extends Model
{
    protected $table = 'resultado_final';

    protected $primaryKey = 'id_resultado';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'detalle' => 'array',
        ];
    }
}
