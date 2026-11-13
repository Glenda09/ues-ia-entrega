<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `autoridad_documental_historial`.
 */
class AutoridadDocumentalHistorial extends Model
{
    protected $table = 'autoridad_documental_historial';

    protected $primaryKey = 'id_autoridad_historial';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'instante' => 'datetime',
        ];
    }
}
