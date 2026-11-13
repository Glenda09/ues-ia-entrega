<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ia_ejecucion`.
 */
class IaEjecucion extends Model
{
    protected $table = 'ia_ejecucion';

    protected $primaryKey = 'id_ejecucion';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'reservada', 'inferencias' => 0, 'tokens_entrada' => 0, 'tokens_salida' => 0, 'costo_reservado' => '0', 'costo_real' => '0'];

    protected function casts(): array
    {
        return [
            'resultado' => 'array',
            'simulado' => 'boolean',
        ];
    }
}
