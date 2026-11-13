<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `academico_idempotencia`.
 */
class Idempotencia extends Model
{
    protected $table = 'academico_idempotencia';

    protected $primaryKey = 'id_idempotencia';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'respuesta' => 'array',
        ];
    }
}
