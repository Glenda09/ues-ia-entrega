<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

use App\Models\Seguridad\SeguridadAccion;
use App\Models\Seguridad\SeguridadGrupo;

class SeguridadGrupoAccion extends Model
{
    protected $table = 'seguridad_grupo_accion';

    protected $fillable = [
        'id_grupo',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $primaryKey = 'id_grupo';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function acciones()
    {
        return $this->hasMany(SeguridadAccion::class, 'id_grupo', 'id_grupo');
    }

    public function grupo()
    {
        return $this->belongsTo(SeguridadGrupo::class, 'id_grupo', 'id_grupo');
    }

    /**
     * Limita la consulta a grupos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
