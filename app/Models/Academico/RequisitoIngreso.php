<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `requisito_ingreso`.
 */
class RequisitoIngreso extends Model
{
    protected $table = 'requisito_ingreso';

    protected $primaryKey = 'id_requisito';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'condicion' => 'array',
            'tipos_permitidos' => 'array',
            'obligatorio' => 'boolean',
        ];
    }
}
