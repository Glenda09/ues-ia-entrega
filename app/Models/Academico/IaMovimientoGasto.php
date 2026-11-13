<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ia_movimiento_gasto`.
 */
class IaMovimientoGasto extends Model
{
    protected $table = 'ia_movimiento_gasto';

    protected $primaryKey = 'id_movimiento';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
        ];
    }
}
