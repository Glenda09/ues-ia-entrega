<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `expediente`.
 */
class Expediente extends Model
{
    protected $table = 'expediente';

    protected $primaryKey = 'id_expediente';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'ACTIVO', 'version' => 1];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date:Y-m-d',
        ];
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante');
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'id_carrera');
    }

    public function pensum()
    {
        return $this->belongsTo(Pensum::class, 'id_pensum');
    }

    public function eventos()
    {
        return $this->hasMany(ExpedienteEvento::class, 'id_expediente');
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'id_expediente');
    }
}
