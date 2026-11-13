<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeguridadMenuOpcionPermiso extends Model
{
    use SoftDeletes;

    protected $table = 'seguridad_menu_opcion_permiso';
    protected $primaryKey = null; // deshabilitar primary key
    public $incrementing = false; // deshabilitar auto-incremento
    public $timestamps = false; // solo created_at + deleted_at

    protected $fillable = [
        'id_permiso',
        'id_opcion',
        'creado_por',
        'created_at',
        'deleted_at',
    ];
}

