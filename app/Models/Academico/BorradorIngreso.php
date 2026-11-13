<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `borrador_ingreso`.
 */
class BorradorIngreso extends Model
{
    protected $table = 'borrador_ingreso';

    protected $primaryKey = 'id_borrador';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['etapa' => 1, 'version' => 1];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'anexos' => 'array',
        ];
    }
}
