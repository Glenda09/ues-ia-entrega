<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

use App\Models\Seguridad\SeguridadAccion;

class SeguridadPerfil extends Model
{
    protected $table = 'seguridad_perfil';
    protected $primaryKey = 'codigo';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'creado_por',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Obtener las acciones asociadas con el perfil
     */
    public function acciones()
    {
        // Pivot requiere creado_por y timestamps no nulos
        return $this->belongsToMany(SeguridadAccion::class, 'seguridad_perfil_accion', 'codigo_perfil', 'id_accion')
            ->withPivot('creado_por')
            ->withTimestamps();
    }
}
