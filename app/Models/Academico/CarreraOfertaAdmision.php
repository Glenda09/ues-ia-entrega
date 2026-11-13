<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `carrera_oferta_admision`.
 */
class CarreraOfertaAdmision extends Model
{
    protected $table = 'carrera_oferta_admision';

    protected $primaryKey = 'id_oferta_admision';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
        ];
    }
}
