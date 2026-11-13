<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ciclo_ajuste`.
 */
class CicloAjuste extends Model
{
    protected $table = 'ciclo_ajuste';

    protected $primaryKey = 'id_ciclo_ajuste';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'anterior' => 'array',
            'nuevo' => 'array',
            'instante' => 'datetime',
        ];
    }
}
