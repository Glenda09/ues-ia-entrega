<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `asignacion_docente`.
 */
class AsignacionDocente extends Model
{
    protected $table = 'asignacion_docente';

    protected $primaryKey = 'id_asignacion_docente';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'desde' => 'datetime',
            'hasta' => 'datetime',
        ];
    }

    public function docente()
    {
        return $this->belongsTo(Docente::class, 'id_docente');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }
}
