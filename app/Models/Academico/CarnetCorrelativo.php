<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `carnet_correlativo`.
 */
class CarnetCorrelativo extends Model
{
    protected $table = 'carnet_correlativo';

    protected $primaryKey = 'id_carnet_correlativo';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
        ];
    }
}
