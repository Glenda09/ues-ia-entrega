<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `sede`.
 */
class Sede extends Model
{
    protected $table = 'sede';

    protected $primaryKey = 'id_sede';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'activa', 'version' => 1];

    protected function casts(): array
    {
        return [
        ];
    }
}
