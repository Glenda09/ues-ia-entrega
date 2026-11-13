<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `expediente_evento`.
 */
class ExpedienteEvento extends Model
{
    protected $table = 'expediente_evento';

    protected $primaryKey = 'id_expediente_evento';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'instante' => 'datetime',
        ];
    }
}
