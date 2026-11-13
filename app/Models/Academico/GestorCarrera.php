<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `gestor_carrera`.
 */
class GestorCarrera extends Model
{
    protected $table = 'gestor_carrera';

    protected $primaryKey = 'id_gestor_carrera';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
        ];
    }
}
