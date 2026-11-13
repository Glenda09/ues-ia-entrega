<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `correccion_ingreso`.
 */
class CorreccionIngreso extends Model
{
    protected $table = 'correccion_ingreso';

    protected $primaryKey = 'id_correccion';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'consumido_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }
}
