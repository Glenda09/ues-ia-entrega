<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `pensum_requisito`.
 */
class PensumRequisito extends Model
{
    protected $table = 'pensum_requisito';

    protected $primaryKey = 'id_pensum_requisito';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
        ];
    }
}
