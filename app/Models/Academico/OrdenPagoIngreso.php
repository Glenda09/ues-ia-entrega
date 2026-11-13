<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `orden_pago_ingreso`.
 */
class OrdenPagoIngreso extends Model
{
    protected $table = 'orden_pago_ingreso';

    protected $primaryKey = 'id_orden_pago';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'pendiente', 'version' => 1];

    protected function casts(): array
    {
        return [
        ];
    }
}
