<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `entrega_acceso`.
 */
class EntregaAcceso extends Model
{
    protected $table = 'entrega_acceso';

    protected $primaryKey = 'id_entrega';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'pendiente', 'intentos' => 0];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'secreto_expira_en' => 'datetime',
            'enviado_en' => 'datetime',
        ];
    }
}
