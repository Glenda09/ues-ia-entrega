<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `autoridad_documental`.
 */
class AutoridadDocumental extends Model
{
    protected $table = 'autoridad_documental';

    protected $primaryKey = 'id_autoridad';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
        ];
    }
}
