<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `inscripcion_evento`.
 */
class InscripcionEvento extends Model
{
    protected $table = 'inscripcion_evento';

    protected $primaryKey = 'id_inscripcion_evento';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'instante' => 'datetime',
        ];
    }
}
