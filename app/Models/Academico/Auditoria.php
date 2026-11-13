<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `academico_auditoria`.
 */
class Auditoria extends Model
{
    protected $table = 'academico_auditoria';

    protected $primaryKey = 'id_auditoria';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metadatos' => 'array',
            'instante' => 'datetime',
        ];
    }
}
