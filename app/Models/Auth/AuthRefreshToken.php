<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthRefreshToken extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'auth_refresh_token';
    protected $primaryKey = 'id_refresh_token';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'id_refresh_token',
        'id_usuario',
        'token_hash',
        'expires_at',
        'revoked',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked' => 'boolean',
    ];
}

