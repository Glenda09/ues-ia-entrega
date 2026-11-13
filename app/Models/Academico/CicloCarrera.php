<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ciclo_carrera`.
 */
class CicloCarrera extends Model
{
    protected $table = 'ciclo_carrera';

    protected $primaryKey = 'id_ciclo_carrera';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'borrador', 'version' => 1];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'configuracion_aplicada' => 'array',
        ];
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'id_carrera');
    }

    public function ventanas()
    {
        return $this->hasMany(VentanaCiclo::class, 'id_ciclo_carrera');
    }

    public function ofertas()
    {
        return $this->hasMany(OfertaMateria::class, 'id_ciclo_carrera');
    }
}
