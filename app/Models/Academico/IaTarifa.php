<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ia_tarifa`.
 */
class IaTarifa extends Model
{
    protected $table = 'ia_tarifa';

    protected $primaryKey = 'id_tarifa';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'datetime',
        ];
    }
}
