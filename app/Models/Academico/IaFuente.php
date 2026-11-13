<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ia_fuente`.
 */
class IaFuente extends Model
{
    protected $table = 'ia_fuente';

    protected $primaryKey = 'id_fuente';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'publicada'];

    protected function casts(): array
    {
        return [
            'vigente_hasta' => 'datetime',
            'publicado_en' => 'datetime',
            'retirado_en' => 'datetime',
        ];
    }
}
