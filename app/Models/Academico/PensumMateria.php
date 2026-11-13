<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `pensum_materia`.
 */
class PensumMateria extends Model
{
    protected $table = 'pensum_materia';

    protected $primaryKey = 'id_pensum_materia';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'obligatoria' => 'boolean',
        ];
    }

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'id_materia');
    }
}
