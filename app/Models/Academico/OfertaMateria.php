<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `oferta_materia`.
 */
class OfertaMateria extends Model
{
    protected $table = 'oferta_materia';

    protected $primaryKey = 'id_oferta_materia';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
        ];
    }

    public function ciclo()
    {
        return $this->belongsTo(CicloCarrera::class, 'id_ciclo_carrera');
    }

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'id_materia');
    }

    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'id_oferta_materia');
    }
}
