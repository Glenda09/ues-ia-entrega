<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `solicitud_ingreso`.
 */
class SolicitudIngreso extends Model
{
    protected $table = 'solicitud_ingreso';

    protected $primaryKey = 'id_solicitud';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'recibida', 'version' => 1, 'revision_actual' => 1];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date:Y-m-d',
        ];
    }

    public function convocatoria()
    {
        return $this->belongsTo(ConvocatoriaIngreso::class, 'id_convocatoria');
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'id_carrera');
    }

    public function revisiones()
    {
        return $this->hasMany(SolicitudIngresoRevision::class, 'id_solicitud');
    }

    public function decisiones()
    {
        return $this->hasMany(DecisionIngreso::class, 'id_solicitud');
    }
}
