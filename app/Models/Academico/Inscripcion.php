<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `inscripcion`.
 */
class Inscripcion extends Model
{
    protected $table = 'inscripcion';

    protected $primaryKey = 'id_inscripcion';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'activa', 'version' => 1];

    protected function casts(): array
    {
        return [
            'inscrito_en' => 'datetime',
            'retirado_en' => 'datetime',
        ];
    }

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'id_expediente');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'id_materia');
    }

    public function ciclo()
    {
        return $this->belongsTo(CicloCarrera::class, 'id_ciclo_carrera');
    }

    public function resultado()
    {
        return $this->hasOne(ResultadoFinal::class, 'id_inscripcion');
    }

    public function eventos()
    {
        return $this->hasMany(InscripcionEvento::class, 'id_inscripcion');
    }
}
