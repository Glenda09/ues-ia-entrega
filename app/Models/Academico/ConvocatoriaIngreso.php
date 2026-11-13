<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `convocatoria_ingreso`.
 */
class ConvocatoriaIngreso extends Model
{
    protected $table = 'convocatoria_ingreso';

    protected $primaryKey = 'id_convocatoria';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'borrador', 'version' => 1, 'version_requisitos' => 1, 'requiere_pago' => true];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'publicado_en' => 'datetime',
            'requiere_pago' => 'boolean',
        ];
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'id_carrera');
    }

    public function requisitos()
    {
        return $this->hasMany(RequisitoIngreso::class, 'id_convocatoria');
    }

    public function ofertasAdmision()
    {
        return $this->hasMany(CarreraOfertaAdmision::class, 'id_convocatoria');
    }
}
