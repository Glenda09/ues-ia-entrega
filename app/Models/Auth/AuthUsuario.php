<?php

namespace App\Models\Auth;

use App\Models\App\Estado;
use App\Models\Seguridad\SeguridadPerfil;
use App\Services\Audit\SessionAuditRepository;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class AuthUsuario extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $table = 'auth_usuario';

    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'username',
        'email',
        'password',
        'fecha_verificacion',
        'ultima_sesion',
        'temporal_token',
        'temporal_token_expira_en',
        'tfa_secret',
        '2fa_tipo',
        'tfa_activo',
        'creado_por',
        'id_estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'tfa_secret',
        'temporal_token',
        'temporal_token_expira_en',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'temporal_token_expira_en' => 'datetime',
            'password_cambio_requerido' => 'boolean',
            'password_temporal_expira_en' => 'datetime',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'perfiles' => $this->perfiles()->pluck('nombre')->toArray(),
        ];
    }

    public function perfiles()
    {
        return $this->belongsToMany(
            SeguridadPerfil::class,
            'auth_usuario_perfil',
            'id_usuario',      // Foreign key on pivot pointing to this model
            'codigo_perfil',   // Foreign key on pivot pointing to related model
            'id_usuario',      // Local key on this model
            'codigo'           // Local key on related model
        )->withPivot('creado_por')
            ->whereNull('auth_usuario_perfil.deleted_at')
            ->withTimestamps();
    }

    public function menu($jti = [])
    {
        if ($jti) {
            try {
                return app(SessionAuditRepository::class)->menuByUserAndJti($this->id_usuario, (string) $jti);
            } catch (\Throwable $e) {
                Log::warning('No se pudo obtener menú desde el backend de sesiones', ['err' => $e->getMessage()]);

                return [];
            }
        }

        return [];
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'id_estado', 'codigo');
    }
}
