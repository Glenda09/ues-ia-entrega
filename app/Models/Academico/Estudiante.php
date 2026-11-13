<?php

namespace App\Models\Academico;

use App\Models\Auth\AuthUsuario;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `estudiante`.
 */
class Estudiante extends Model
{
    protected $table = 'estudiante';

    protected $primaryKey = 'id_estudiante';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['version' => 1];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date:Y-m-d',
        ];
    }

    public function expedientes()
    {
        return $this->hasMany(Expediente::class, 'id_estudiante');
    }

    public function usuario()
    {
        return $this->belongsTo(AuthUsuario::class, 'id_usuario', 'id_usuario');
    }
}
