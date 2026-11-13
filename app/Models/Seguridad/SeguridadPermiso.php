<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

use App\Models\Seguridad\SeguridadAccion;

class SeguridadPermiso extends Model
{
    protected $table = 'seguridad_permiso';
    protected $primaryKey = 'permiso';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'permiso',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Obtener las acciones asociadas con el permiso
     */
    public function acciones()
    {
        return $this->belongsToMany(SeguridadAccion::class, 'seguridad_permiso_accion', 'permiso', 'id_accion');
    }

    /**
     * Limita la consulta a permisos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Limita la consulta a permisos inactivos
     */
    public function scopeInactivos($query)
    {
        return $query->where('activo', false);
    }
}
