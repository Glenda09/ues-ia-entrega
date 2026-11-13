<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `documento_expediente`.
 */
class DocumentoExpediente extends Model
{
    protected $table = 'documento_expediente';

    protected $primaryKey = 'id_documento';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'pendiente', 'intentos' => 0];

    protected function casts(): array
    {
        return [
            'instantanea' => 'array',
            'identidad_institucional' => 'array',
            'emitido_en' => 'datetime',
        ];
    }
}
