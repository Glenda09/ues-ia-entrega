<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `grupo`.
 */
class Grupo extends Model
{
    protected $table = 'grupo';

    protected $primaryKey = 'id_grupo';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'borrador', 'version' => 1];

    protected function casts(): array
    {
        return [
        ];
    }

    public function oferta()
    {
        return $this->belongsTo(OfertaMateria::class, 'id_oferta_materia');
    }

    public function horarios()
    {
        return $this->hasMany(HorarioGrupo::class, 'id_grupo');
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionDocente::class, 'id_grupo');
    }

    public function acta()
    {
        return $this->hasOne(ActaGrupo::class, 'id_grupo');
    }
}
