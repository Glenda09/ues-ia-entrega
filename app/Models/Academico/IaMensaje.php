<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ia_mensaje`.
 */
class IaMensaje extends Model
{
    protected $table = 'ia_mensaje';

    protected $primaryKey = 'id_mensaje';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'referencias' => 'array',
        ];
    }
}
