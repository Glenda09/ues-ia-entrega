<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ia_presupuesto`.
 */
class IaPresupuesto extends Model
{
    protected $table = 'ia_presupuesto';

    protected $primaryKey = 'id_presupuesto';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'incidente_tarifa' => 'boolean',
        ];
    }
}
