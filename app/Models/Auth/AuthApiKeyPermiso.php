<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

use App\Models\Seguridad\SeguridadPermiso;

class AuthApiKeyPermiso extends Model
{
    protected $table = 'auth_api_key_permiso';
    protected $primaryKey = ['api_key', 'id_permiso'];
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'api_key',
        'id_permiso',
        'creado_por',
        'created_at',
    ];

    public function apiKey()
    {
        return $this->belongsTo(AuthApiKey::class, 'api_key', 'api_key');
    }

    public function permiso()
    {
        return $this->belongsTo(SeguridadPermiso::class, 'id_permiso', 'permiso');
    }
}

