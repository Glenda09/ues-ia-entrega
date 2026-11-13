<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `acta_grupo`.
 */
class ActaGrupo extends Model
{
    protected $table = 'acta_grupo';

    protected $primaryKey = 'id_acta';

    protected $guarded = [];

    /** Valores por defecto alineados con la migración. */
    protected $attributes = ['estado' => 'borrador', 'version' => 1];

    protected function casts(): array
    {
        return [
            'calculado_en' => 'datetime',
            'publicado_en' => 'datetime',
        ];
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }

    public function escala()
    {
        return $this->belongsTo(EscalaCalificacion::class, 'id_escala');
    }

    public function resultados()
    {
        return $this->hasMany(ResultadoFinal::class, 'id_acta');
    }
}
