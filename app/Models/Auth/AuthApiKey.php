<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class AuthApiKey extends Model
{
    protected $table = 'auth_api_key';

    protected $primaryKey = 'api_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'api_key',
        'token_hash',
        'cliente',
        'id_estado',
        'created_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * Permisos asociados a la API Key
     */
    public function permisos()
    {
        return $this->belongsToMany(
            \App\Models\Seguridad\SeguridadPermiso::class,
            'auth_api_key_permiso',
            'api_key',
            'id_permiso',
            'api_key',
            'permiso'
        );
    }
}
