<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

use App\Models\Seguridad\SeguridadPermiso;
use App\Models\Seguridad\SeguridadAccion;

class SeguridadPermisoAccion extends Model
{
    protected $table = 'seguridad_permiso_accion';
    protected $primaryKey = ['permiso', 'id_accion'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'permiso',
        'id_accion',
    ];

    /**
     * Relación con el modelo SeguridadPermiso
     */
    public function permiso()
    {
        return $this->belongsTo(SeguridadPermiso::class, 'permiso', 'permiso');
    }

    /**
     * Relación con el modelo SeguridadAccion
     */
    public function accion()
    {
        return $this->belongsTo(SeguridadAccion::class, 'id_accion', 'id_accion');
    }
}
