<?php

namespace App\Models\Auth;

use App\Models\Seguridad\SeguridadPerfil;
use Illuminate\Database\Eloquent\Model;

class AuthUsuarioPerfil extends Model
{
    protected $table = 'auth_usuario_perfil';

    protected $primaryKey = ['id_usuario', 'codigo_perfil'];

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'id_usuario',
        'codigo_perfil',
        'creado_por',
    ];

    /**
     * Define the relationship with the AuthUsuario model.
     */
    public function usuario()
    {
        return $this->belongsTo(AuthUsuario::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Define the relationship with the SeguridadPerfil model.
     */
    public function perfil()
    {
        return $this->belongsTo(SeguridadPerfil::class, 'codigo_perfil', 'codigo');
    }
}
