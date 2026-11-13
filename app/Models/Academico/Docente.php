<?php

namespace App\Models\Academico;

use App\Models\Auth\AuthUsuario;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `docente`.
 */
class Docente extends Model
{
    protected $table = 'docente';

    protected $primaryKey = 'id_docente';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'activo', 'version' => 1];

    protected function casts(): array
    {
        return [
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(AuthUsuario::class, 'id_usuario', 'id_usuario');
    }
}
