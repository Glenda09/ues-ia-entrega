<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

use App\Models\App\AppModulo;
use App\Models\App\Estado;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Seguridad\SeguridadPermiso;

class SeguridadMenuOpcion extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'seguridad_menu_opcion';
    protected $primaryKey = 'id_opcion';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'id_opcion',
        'id_modulo',
        'id_opcion_padre',
        'nombre',
        'ruta',
        'icono',
        'desplegable',
        'orden',
        'id_estado',
    ];

    public function modulo()
    {
        return $this->belongsTo(AppModulo::class, 'id_modulo', 'codigo');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado', 'codigo');
    }

    public function padre()
    {
        return $this->belongsTo(self::class, 'id_opcion_padre', 'id_opcion');
    }

    public function hijos()
    {
        return $this->hasMany(self::class, 'id_opcion_padre', 'id_opcion');
    }

    public function scopeActivos($query)
    {
        return $query->where('id_estado', Estado::ACTIVO);
    }

    /**
     * Permisos asociados a la opción de menú.
     */
    public function permisos()
    {
        return $this->belongsToMany(
            SeguridadPermiso::class,
            'seguridad_menu_opcion_permiso',
            'id_opcion',      // FK pivot -> opción
            'id_permiso',     // FK pivot -> permiso
            'id_opcion',      // Clave local
            'permiso'         // Clave del modelo SeguridadPermiso
        )->withPivot(['creado_por', 'created_at', 'deleted_at'])
            ->wherePivotNull('deleted_at');
    }
}
