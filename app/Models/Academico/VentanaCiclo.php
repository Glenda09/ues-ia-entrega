<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `ventana_ciclo`.
 */
class VentanaCiclo extends Model
{
    protected $table = 'ventana_ciclo';

    protected $primaryKey = 'id_ventana_ciclo';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin_regular' => 'datetime',
            'fin_gracia' => 'datetime',
        ];
    }
}
