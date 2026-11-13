<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `carrera`.
 */
class Carrera extends Model
{
    protected $table = 'carrera';

    protected $primaryKey = 'id_carrera';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'activa', 'version' => 1, 'clasificacion' => 'general'];

    protected function casts(): array
    {
        return [
        ];
    }

    public function sedes()
    {
        return $this->belongsToMany(Sede::class, 'carrera_sede', 'id_carrera', 'id_sede');
    }

    public function facultad()
    {
        return $this->belongsTo(Facultad::class, 'id_facultad');
    }

    public function pensums()
    {
        return $this->hasMany(Pensum::class, 'id_carrera');
    }

    public function gestores()
    {
        return $this->hasMany(GestorCarrera::class, 'id_carrera');
    }
}
