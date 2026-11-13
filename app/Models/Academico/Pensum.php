<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `pensum`.
 */
class Pensum extends Model
{
    protected $table = 'pensum';

    protected $primaryKey = 'id_pensum';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'borrador', 'version' => 1];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date:Y-m-d',
            'vigente_hasta' => 'date:Y-m-d',
            'publicado_en' => 'datetime',
        ];
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'id_carrera');
    }

    public function materias()
    {
        return $this->hasMany(PensumMateria::class, 'id_pensum');
    }

    public function requisitos()
    {
        return $this->hasMany(PensumRequisito::class, 'id_pensum');
    }

    public function equivalencias()
    {
        return $this->hasMany(PensumEquivalencia::class, 'id_pensum');
    }
}
