<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `solicitud_ingreso_revision`.
 */
class SolicitudIngresoRevision extends Model
{
    protected $table = 'solicitud_ingreso_revision';

    protected $primaryKey = 'id_revision';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'instante' => 'datetime',
        ];
    }

    public function anexos()
    {
        return $this->hasMany(SolicitudAnexo::class, 'id_revision');
    }
}
