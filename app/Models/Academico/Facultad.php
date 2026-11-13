<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `facultad`.
 */
class Facultad extends Model
{
    protected $table = 'facultad';

    protected $primaryKey = 'id_facultad';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'activa', 'version' => 1];

    protected function casts(): array
    {
        return [
        ];
    }
}
