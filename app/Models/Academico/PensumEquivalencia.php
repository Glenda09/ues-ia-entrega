<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `pensum_equivalencia`.
 */
class PensumEquivalencia extends Model
{
    protected $table = 'pensum_equivalencia';

    protected $primaryKey = 'id_pensum_equivalencia';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
        ];
    }
}
