<?php

namespace App\Services\Auth;

use App\Models\Auth\AuthUsuario;
use App\Services\App\PersonalizacionService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private Google2FA $google2FA = new Google2FA) {}

    public function createSecret(AuthUsuario $user): array
    {
        $secret = $this->google2FA->generateSecretKey();
        $user->{'tfa_secret'} = Crypt::encryptString($secret);
        $user->save();

        $company = (string) (app(PersonalizacionService::class)->publica()['textos']['nombre'] ?? '');
        $identifier = (string) ($user->email ?: $user->username ?: $user->id_usuario);
        $issuer = $company !== '' ? $company : Config::get('app.url', 'Laravel App');

        $otpauthUrl = $this->google2FA->getQRCodeUrl($issuer, $identifier, $secret);

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
        ];
    }

    public function ensureSecret(AuthUsuario $user): array
    {
        if ($user->{'tfa_secret'}) {
            try {
                $secret = Crypt::decryptString($user->{'tfa_secret'});
            } catch (\Throwable $e) {
                return $this->createSecret($user);
            }

            $company = (string) (app(PersonalizacionService::class)->publica()['textos']['nombre'] ?? '');
            $identifier = (string) ($user->email ?: $user->username ?: $user->id_usuario);
            $issuer = $company !== '' ? $company : Config::get('app.url', 'Laravel App');

            return [
                'secret' => $secret,
                'otpauth_url' => $this->google2FA->getQRCodeUrl($issuer, $identifier, $secret),
            ];
        }

        return $this->createSecret($user);
    }

    public function verifyToken(AuthUsuario $user, string $token, int $window = 1): bool
    {
        $secret = $this->getDecryptedSecret($user);
        if ($secret === null) {
            return false;
        }

        return $this->google2FA->verifyKey($secret, $token, $window);
    }

    public function politicaActiva(): bool
    {
        return (bool) config('auth.two_factor_enforce_all', true);
    }

    public function estaExigido(AuthUsuario $user): bool
    {
        return $this->politicaActiva() || (bool) $user->{'tfa_activo'};
    }

    public function metodoObligatorio(): string
    {
        $metodo = strtolower(trim((string) config('auth.two_factor_required_method', 'email')));

        return in_array($metodo, ['email', 'authenticator'], true) ? $metodo : 'email';
    }

    public function cumplePolitica(AuthUsuario $user): bool
    {
        return (bool) $user->{'tfa_activo'}
            && ($user->{'2fa_tipo'} === 'authenticator' || $user->{'2fa_tipo'} === $this->metodoObligatorio());
    }

    public function configuracionPendiente(AuthUsuario $user): bool
    {
        return $this->politicaActiva() && $this->metodoObligatorio() === 'authenticator' && ! $this->cumplePolitica($user);
    }

    public function puedeDesactivar(): bool
    {
        return ! $this->politicaActiva();
    }

    public function activarCorreo(AuthUsuario $user): void
    {
        $user->{'tfa_secret'} = null;
        $this->activate($user, 'email');
    }

    public function clear(AuthUsuario $user): void
    {
        $user->{'tfa_secret'} = null;
        $user->{'tfa_activo'} = false;
        $user->{'2fa_tipo'} = null;
        $user->temporal_token = null;
        $user->temporal_token_expira_en = null;
        $user->save();
    }

    public function activate(AuthUsuario $user, string $type = 'authenticator'): void
    {
        $user->{'tfa_activo'} = true;
        $user->{'2fa_tipo'} = $type;
        $user->temporal_token = null;
        $user->temporal_token_expira_en = null;
        $user->save();
    }

    private function getDecryptedSecret(AuthUsuario $user): ?string
    {
        $secret = $user->{'tfa_secret'};
        if (! $secret) {
            return null;
        }

        try {
            return Crypt::decryptString($secret);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
