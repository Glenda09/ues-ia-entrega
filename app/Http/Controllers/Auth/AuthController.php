<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\AuthRefreshToken;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\PasswordResetToken;
use App\Services\App\PersonalizacionService;
use App\Services\Auth\SesionUsuarioService;
use App\Services\Auth\TwoFactorService;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private EmailService $emailService,
        private SesionUsuarioService $sesionUsuarioService,
        private TwoFactorService $twoFactorService
    ) {}

    public function login(Request $request)
    {
        $credenciales = $request->validate([
            'identifier' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        $identifier = strtolower(trim($credenciales['identifier']));
        $password = $credenciales['password'];

        try {
            $user = AuthUsuario::where('email', $identifier)->orWhere('username', $identifier)->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                return response()->json(['message' => 'Credenciales inválidas'], 401);
            }

            if ($this->twoFactorService->estaExigido($user)) {
                $type = $user->{'2fa_tipo'} ?? 'email';

                if ($type === 'authenticator' && (bool) $user->{'tfa_activo'}) {
                    if (! $user->{'tfa_secret'}) {
                        return response()->json(['message' => 'La configuración de doble factor es inválida'], 500);
                    }

                    $user->temporal_token = null;
                    $user->temporal_token_expira_en = null;
                    $user->save();

                    return response()->json([
                        'message' => 'Ingresa el código generado por Google Authenticator',
                        'methods' => ['authenticator'],
                    ], 202);
                }

                $token2fa = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $user->temporal_token_expira_en = now()->addMinutes(max(1, (int) config('auth.two_factor_email_code_ttl_minutes', 5)));
                $user->temporal_token = $token2fa;
                $user->save();

                $subject = 'Código de verificación 2FA';
                $html = sprintf(
                    'Hola %s!<br><br>Tu código de verificación es: <b>%s</b>.<br>Ingresa este código para completar tu inicio de sesión.<br><br>Equipo de %s',
                    e($user->username ?? ''),
                    e($token2fa),
                    e(app(PersonalizacionService::class)->publica()['textos']['nombre'] ?? '')
                );

                $this->emailService->sendHtmlEmail($user->email, $subject, $html);

                return response()->json([
                    'message' => 'Ingresa el código enviado a tu correo',
                    'methods' => ['email'],
                ], 202);
            }

            // 2FA no activo, emitir JWT
            $token = Auth::guard('api')->login($user);
            $user->ultima_sesion = now();
            $user->temporal_token = null;
            $user->temporal_token_expira_en = null;
            $user->save();

            if (! $token) {
                return response()->json(['message' => 'No se pudo generar el token de autenticación'], 500);
            }

            // Registrar sesión de auditoría (acciones y permisos)
            $this->sesionUsuarioService->crearSesion($user, $token, $request);

            // Generar y devolver refresh token
            $refreshToken = $this->emitirRefreshToken($user, $request);

            return $this->respondWithTokens($token, $refreshToken);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo procesar la solicitud'], 500);
        }
    }

    public function refresh()
    {
        return $this->respondWithToken(Auth::guard('api')->refresh());
    }

    protected function respondWithToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60, // tiempo en segundos
        ]);
    }

    protected function respondWithTokens(string $accessToken, string $refreshToken)
    {
        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ]);
    }

    public function refreshToken(Request $request)
    {
        $provided = (string) $request->input('refresh_token', '');

        if ($provided === '') {
            return response()->json(['message' => 'Token de refresco no proporcionado'], 401);
        }

        try {
            $hash = hash('sha256', $provided);
            $record = AuthRefreshToken::query()
                ->where('token_hash', $hash)
                ->where('revoked', false)
                ->first();

            if (! $record) {
                return response()->json(['message' => 'El token de refresco es inválido'], 401);
            }

            if ($record->expires_at !== null && now()->greaterThan($record->expires_at)) {
                return response()->json(['message' => 'El token de refresco ha expirado'], 401);
            }

            $user = AuthUsuario::query()->find($record->id_usuario);
            if (! $user) {
                return response()->json(['message' => 'Usuario no encontrado'], 404);
            }

            // Rotar refresh token
            $this->revocarRefreshToken($record);
            $newRefresh = $this->emitirRefreshToken($user, $request);

            // Emitir nuevo access token y registrar sesión
            $newAccess = Auth::guard('api')->login($user);

            if (! $newAccess) {
                return response()->json(['message' => 'No se pudo generar el token de autenticación'], 500);
            }

            $this->sesionUsuarioService->crearSesion($user, $newAccess, $request);

            return $this->respondWithTokens($newAccess, $newRefresh);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo procesar la solicitud'], 500);
        }
    }

    public function logout()
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function requestPasswordRecovery(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:auth_usuario,email',
        ]);

        $email = strtolower($data['email']);

        // Verifica intentos previos
        $cacheKey = 'password_recovery_'.$email;
        $attempts = (int) Cache::get($cacheKey, 0);
        if ($attempts >= 3) {
            return response()->json(['message' => 'Intente nuevamente más tarde'], 400);
        }

        $user = AuthUsuario::where('email', $email)->firstOrFail();

        $token = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        PasswordResetToken::updateOrCreate(
            ['email' => $email],
            ['token' => $token, 'created_at' => now()]
        );

        $subject = 'Código para recuperación de cuenta';
        $ttl = (int) config('auth.password_reset_ttl_minutes', 5);
        $html = sprintf(
            'Hola %s!<br><br>El código para la recuperación de tu cuenta es: <b>%s</b>, este código estará vigente por los próximos %d minutos<br><br>Equipo de %s',
            e($user->username ?? ''),
            e($token),
            $ttl,
            e(app(PersonalizacionService::class)->publica()['textos']['nombre'] ?? '')
        );

        $this->emailService->sendHtmlEmail($email, $subject, $html);

        // Incrementa el contador de intentos
        Cache::put($cacheKey, $attempts + 1, now()->addMinutes(config('auth.password_reset_attempt_window_minutes', 10)));

        return response()->json(['message' => 'Se ha enviado un código de verificación a su correo']);
    }

    public function validateRecoveryToken(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:auth_usuario,email',
            'token' => 'required|digits:6',
        ]);

        $email = strtolower($data['email']);
        $token = $data['token'];

        $cacheKey = 'password_recovery_token_'.$email;
        $attempts = (int) Cache::get($cacheKey, 0);
        if ($attempts >= 3) {
            return response()->json(['message' => 'Intente nuevamente más tarde'], 400);
        }

        $user = AuthUsuario::where('email', $email)->firstOrFail();

        $record = PasswordResetToken::where('email', $email)->first();
        if (! $record || $record->token !== $token) {
            Cache::put($cacheKey, $attempts + 1, now()->addMinutes(config('auth.password_reset_attempt_window_minutes', 10)));

            return response()->json(['message' => 'El token proporcionado es inválido'], 401);
        }

        $createdAt = $record->created_at instanceof \DateTimeInterface
            ? Carbon::instance($record->created_at)
            : Carbon::parse((string) $record->created_at);
        $ttl = (int) config('auth.password_reset_ttl_minutes', 5);
        if ($createdAt->lt(now()->subMinutes($ttl))) {
            Cache::put($cacheKey, $attempts + 1, now()->addMinutes(config('auth.password_reset_attempt_window_minutes', 10)));

            return response()->json(['message' => 'El token proporcionado ha expirado'], 401);
        }

        PasswordResetToken::where('email', $email)->delete();
        $newPassword = substr(bin2hex(random_bytes(8)), 0, 8);
        $user->password = Hash::make($newPassword);
        // EST-01: la recuperación emite una nueva clave temporal que exige cambio inicial.
        if ($user->password_cambio_requerido) {
            $user->password_temporal_expira_en = now()->addHours((int) app(\App\Services\Academico\ConfiguracionService::class)->valor('credenciales.password_temporal_horas', 72));
        }
        $user->save();

        $subject = 'Has recuperado tu cuenta';
        $html = sprintf(
            'Hola %s!<br><br>Tu nueva contraseña es: <b>%s</b>, guárdala en un lugar seguro<br><br>Equipo de %s',
            e($user->username ?? ''),
            e($newPassword),
            e(app(PersonalizacionService::class)->publica()['textos']['nombre'] ?? '')
        );

        $this->emailService->sendHtmlEmail($email, $subject, $html);

        return response()->json(['message' => 'Token verificado. Se envió su nueva contraseña al correo registrado']);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|same:password_confirmation',
            'password_confirmation' => 'required',
        ]);

        $user = Auth::guard('api')->user();

        if (! $user || ! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'La contraseña actual no es correcta'], 401);
        }

        // EST-01: la contraseña temporal debe estar vigente; el cambio limpia la obligación en la misma transacción.
        if ($user->password_cambio_requerido && $user->password_temporal_expira_en && $user->password_temporal_expira_en->isPast()) {
            return response()->json(['message' => 'Tu contraseña temporal venció. Usa la recuperación de cuenta.', 'code' => 'ACCESO_TEMPORAL_VENCIDO'], 403);
        }
        if ($user->password_cambio_requerido && Hash::check($data['new_password'], $user->password)) {
            return response()->json(['message' => 'La nueva contraseña debe ser distinta de la temporal', 'errors' => ['new_password' => ['La nueva contraseña debe ser distinta de la temporal']]], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data) {
            $user->password = Hash::make($data['new_password']);
            $user->password_cambio_requerido = false;
            $user->password_temporal_expira_en = null;
            $user->save();
        });

        $subject = 'Tu contraseña se ha actualizado';
        $html = sprintf(
            'Hola %s!<br><br>Tu contraseña ha sido actualizada con éxito, si no fuiste tu te recomendamos que realices el proceso de recuperación de cuenta lo más antes posible<br><br>Equipo de %s',
            e($user->username ?? ''),
            e(app(PersonalizacionService::class)->publica()['textos']['nombre'] ?? '')
        );

        $this->emailService->sendHtmlEmail($user->email, $subject, $html);

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function validarToken2fa(Request $request)
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:255',
            'token' => 'required|digits:6',
        ]);

        $identifier = strtolower(trim($data['identifier']));
        $token = $data['token'];

        try {
            $user = AuthUsuario::where('email', $identifier)->orWhere('username', $identifier)->firstOrFail();

            if (! $this->twoFactorService->estaExigido($user)) {
                return response()->json(['message' => 'El 2FA no está activo para este usuario'], 400);
            }

            // Control de intentos fallidos
            $cacheKey = 'twofa_attempts_'.$identifier;
            $attempts = (int) Cache::get($cacheKey, 0);
            if ($attempts >= 3) {
                return response()->json(['message' => 'Intente nuevamente más tarde'], 400);
            }

            $window = (int) config('auth.two_factor_window', 1);
            $type = $user->{'2fa_tipo'} ?? 'email';
            $valid = false;

            if ($type === 'authenticator' && (bool) $user->{'tfa_activo'}) {
                if (! $user->{'tfa_secret'}) {
                    return response()->json(['message' => 'La configuración de doble factor es inválida'], 500);
                }

                $valid = $this->twoFactorService->verifyToken($user, $token, $window);
            } else {
                if ($user->temporal_token && $user->temporal_token_expira_en?->isFuture() && hash_equals((string) $user->temporal_token, $token)) {
                    $valid = true;
                }
            }

            if (! $valid) {
                Cache::put($cacheKey, $attempts + 1, now()->addMinutes(config('auth.password_reset_attempt_window_minutes', 10)));

                return response()->json(['message' => 'El token proporcionado es inválido'], 401);
            }

            $user->temporal_token = null;
            $user->temporal_token_expira_en = null;
            $user->ultima_sesion = now();
            $user->save();

            if ($this->twoFactorService->politicaActiva() && $this->twoFactorService->metodoObligatorio() === 'email' && ! (bool) $user->{'tfa_activo'}) {
                $this->twoFactorService->activarCorreo($user);
            }

            // Éxito: limpiar contador de intentos
            Cache::forget($cacheKey);

            $jwt = Auth::guard('api')->login($user);

            if (! $jwt) {
                return response()->json(['message' => 'No se pudo generar el token de autenticación'], 500);
            }

            // Registrar sesión de auditoría (acciones y permisos)
            $this->sesionUsuarioService->crearSesion($user, $jwt, $request);

            // Generar y devolver refresh token
            $refreshToken = $this->emitirRefreshToken($user, $request);

            return $this->respondWithTokens($jwt, $refreshToken);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo procesar la solicitud'], 500);
        }
    }

    protected function emitirRefreshToken(AuthUsuario $user, Request $request): string
    {
        $raw = bin2hex(random_bytes(40)); // 80 chars, 320 bits de entropía
        $hash = hash('sha256', $raw);

        $ttlMinutes = (int) config('auth.refresh_token_ttl_minutes', 60 * 24 * 30); // 30 días

        AuthRefreshToken::create([
            'id_usuario' => $user->id_usuario,
            'token_hash' => $hash,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'revoked' => false,
            'ip_address' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return $raw;
    }

    protected function revocarRefreshToken(AuthRefreshToken $record): void
    {
        $record->revoked = true;
        $record->save();
    }
}
