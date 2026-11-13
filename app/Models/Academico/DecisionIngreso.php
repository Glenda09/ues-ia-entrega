<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `decision_ingreso`.
 */
class DecisionIngreso extends Model
{
    protected $table = 'decision_ingreso';

    protected $primaryKey = 'id_decision';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'instante' => 'datetime',
        ];
    }
}
