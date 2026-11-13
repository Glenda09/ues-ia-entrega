<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `horario_grupo`.
 */
class HorarioGrupo extends Model
{
    protected $table = 'horario_grupo';

    protected $primaryKey = 'id_horario_grupo';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
        ];
    }
}
