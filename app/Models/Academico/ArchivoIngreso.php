<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `archivo_ingreso`.
 */
class ArchivoIngreso extends Model
{
    protected $table = 'archivo_ingreso';

    protected $primaryKey = 'id_archivo';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
        ];
    }
}
