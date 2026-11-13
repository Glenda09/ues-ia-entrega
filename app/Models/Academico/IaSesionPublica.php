<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ia_sesion_publica`.
 */
class IaSesionPublica extends Model
{
    protected $table = 'ia_sesion_publica';

    protected $primaryKey = 'id_sesion_publica';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
        ];
    }
}
