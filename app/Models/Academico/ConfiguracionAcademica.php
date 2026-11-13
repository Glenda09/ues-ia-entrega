<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `configuracion_academica`.
 */
class ConfiguracionAcademica extends Model
{
    protected $table = 'configuracion_academica';

    protected $primaryKey = 'id_configuracion';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valores' => 'array',
        ];
    }
}
