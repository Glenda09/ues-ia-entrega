<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

use App\Models\Seguridad\SeguridadGrupoAccion;
use App\Models\Seguridad\SeguridadPermisoAccion;
use App\Models\Seguridad\SeguridadPerfilAccion;
use App\Models\Seguridad\SeguridadPermiso;

class SeguridadAccion extends Model
{
    use HasUuids;

    protected $table = 'seguridad_accion';
    protected $primaryKey = 'id_accion';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_accion',
        'id_grupo',
        'accion',
        'descripcion',
        'activo',
    ];

    public $timestamps = false;

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Define the relationship with the SeguridadGrupoAccion model.
     */
    public function grupo()
    {
        return $this->belongsTo(SeguridadGrupoAccion::class, 'id_grupo', 'id_grupo');
    }

    /**
     * Define the relationship with the SeguridadPermisoAccion model.
     */
    public function permisos()
    {
        return $this->hasMany(SeguridadPermisoAccion::class, 'id_accion', 'id_accion');
    }

    /**
     * Define the relationship with the SeguridadPerfilAccion model.
     */
    public function perfiles()
    {
        return $this->hasMany(SeguridadPerfilAccion::class, 'id_accion', 'id_accion');
    }

    /**
     * Relación muchos a muchos con permisos a través de la tabla pivot seguridad_permiso_accion.
     * Devuelve instancias de SeguridadPermiso.
     */
    public function permisosAsociados()
    {
        return $this->belongsToMany(
            SeguridadPermiso::class,
            'seguridad_permiso_accion',
            'id_accion',
            'permiso'
        );
    }
}
