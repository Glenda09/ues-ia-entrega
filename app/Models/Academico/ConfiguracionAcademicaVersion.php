<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `configuracion_academica_version`.
 */
class ConfiguracionAcademicaVersion extends Model
{
    protected $table = 'configuracion_academica_version';

    protected $primaryKey = 'id_configuracion_version';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'valores' => 'array',
            'instante' => 'datetime',
        ];
    }
}
