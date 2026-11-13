<?php

namespace App\Models\Relational\Auth;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuthSesionUsuarioRelational extends Model
{
    use HasUuids;

    protected $table = 'auth_sesion_usuario';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = true;

    const CREATED_AT = 'creation_date';

    const UPDATED_AT = 'updated_date';

    protected $fillable = [
        'uuid',
        'id_usuario',
        'jti',
        'ip_address',
        'user_agent',
        'acciones',
        'permisos',
        'menu',
        'creation_date',
        'updated_date',
    ];

    protected $casts = [
        'acciones' => 'array',
        'permisos' => 'array',
        'menu' => 'array',
        'creation_date' => 'datetime',
        'updated_date' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection((string) config('audit.relational_connection', config('database.default')));
    }
}
