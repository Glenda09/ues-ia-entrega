<?php

namespace App\Models\Academico;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `solicitud_anexo`.
 */
class SolicitudAnexo extends Model
{
    protected $table = 'solicitud_anexo';

    protected $primaryKey = 'id_anexo';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
        ];
    }

    public function archivo()
    {
        return $this->belongsTo(ArchivoIngreso::class, 'id_archivo');
    }
}
