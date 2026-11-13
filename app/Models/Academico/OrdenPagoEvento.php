<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `orden_pago_evento`.
 */
class OrdenPagoEvento extends Model
{
    protected $table = 'orden_pago_evento';

    protected $primaryKey = 'id_orden_pago_evento';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'instante' => 'datetime',
        ];
    }
}
