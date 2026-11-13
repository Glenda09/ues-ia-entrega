<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

use App\Models\Seguridad\SeguridadPerfil;
use App\Models\Seguridad\SeguridadAccion;

class SeguridadPerfilAccion extends Model
{
    protected $table = 'seguridad_perfil_accion';
    protected $primaryKey = ['codigo_perfil', 'id_accion'];
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'codigo_perfil',
        'id_accion',
    ];

    protected $casts = [
        'codigo_perfil' => 'string',
    ];

    /**
     * Obtener el perfil asociado a la acción
     */
    public function perfil()
    {
        return $this->belongsTo(SeguridadPerfil::class, 'codigo_perfil', 'codigo');
    }

    /**
     * Obtener la acción asociada al perfil
     */
    public function accion()
    {
        return $this->belongsTo(SeguridadAccion::class, 'id_accion', 'id_accion');
    }
}
