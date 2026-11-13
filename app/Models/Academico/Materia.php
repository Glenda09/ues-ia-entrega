<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `materia`.
 */
class Materia extends Model
{
    protected $table = 'materia';

    protected $primaryKey = 'id_materia';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'activa', 'version' => 1];

    protected function casts(): array
    {
        return [
        ];
    }
}
