<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `verificacion_correo_ingreso`.
 */
class VerificacionCorreoIngreso extends Model
{
    protected $table = 'verificacion_correo_ingreso';

    protected $primaryKey = 'id_verificacion';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['intentos' => 0];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'consumido_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }
}
