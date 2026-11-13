<?php

namespace App\Http\Controllers\Cuenta;

use App\Http\Controllers\Controller;
use App\Models\Auth\AuthUsuario;
use App\Services\Auth\SesionUsuarioService;
use App\Services\Auth\TwoFactorService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CuentaController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private SesionUsuarioService $sesionUsuarioService,
    ) {}

    public function info(Request $request)
    {
        try {
            $authUser = ($request->get('auth_user'));
            $permisos = ($request->get('auth_permissions'));
            $user = AuthUsuario::with(['estado', 'perfiles'])->findOrFail($authUser->id_usuario);

            return response()->json([
                'id_usuario' => $user->id_usuario,
                'username' => $user->username,
                'email' => $user->email,
                'estado' => $user->estado?->nombre_estado,
                'perfiles' => $user->perfiles->map(fn ($perfil) => [
                    'codigo' => $perfil->codigo,
                    'nombre' => $perfil->nombre,
                ])->values(),
                'menu' => $this->sesionUsuarioService->construirMenuAutorizado(is_array($permisos) ? $permisos : []),
                'permisos' => $permisos,
                'two_factor_status' => (bool) ($user->{'tfa_activo'} ?? false),
                'two_factor_type' => $user->{'2fa_tipo'} ?? null,
                'two_factor_required' => $this->twoFactorService->politicaActiva(),
                'two_factor_required_method' => $this->twoFactorService->metodoObligatorio(),
                'two_factor_can_disable' => $this->twoFactorService->puedeDesactivar(),
                'two_factor_setup_pending' => $this->twoFactorService->configuracionPendiente($user),
                'password_change_required' => (bool) $user->password_cambio_requerido,
                'temporary_password_expires_at' => $user->password_temporal_expira_en?->toIso8601String(),
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener información de usuario', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function iniciarConfiguracion2FA(Request $request)
    {
        try {
            $authUser = $request->get('auth_user');
            $user = AuthUsuario::query()->findOrFail($authUser->id_usuario);

            if ((bool) ($user->{'tfa_activo'} ?? false) && ($user->{'2fa_tipo'} ?? null) === 'authenticator') {
                return response()->json([
                    'message' => 'El doble factor ya se encuentra habilitado',
                ], Response::HTTP_BAD_REQUEST);
            }

            $payload = $this->twoFactorService->ensureSecret($user);

            return response()->json([
                'message' => 'Clave de doble factor generada',
                'data' => $payload,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Error al iniciar configuración de 2FA', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'No se pudo generar la clave de doble factor'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function alternar2FAEstado(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|string|in:disabled,email,authenticator',
            'token' => 'required_if:mode,authenticator|digits:6',
        ]);

        $mode = $data['mode'];
        if ($mode === 'disabled' && ! $this->twoFactorService->puedeDesactivar()) {
            return response()->json(['message' => 'El doble factor es obligatorio y no se puede desactivar'], 422);
        }
        if ($mode === 'email' && $this->twoFactorService->politicaActiva() && $this->twoFactorService->metodoObligatorio() === 'authenticator') {
            return response()->json(['message' => 'El método obligatorio es Google Authenticator'], 422);
        }

        try {
            $authUser = $request->get('auth_user');
            $user = AuthUsuario::query()->findOrFail($authUser->id_usuario);

            $message = 'Doble factor actualizado';

            if ($mode === 'authenticator') {
                if (! $user->{'tfa_secret'}) {
                    return response()->json([
                        'message' => 'Debe generar la clave de Google Authenticator antes de habilitarla',
                    ], Response::HTTP_BAD_REQUEST);
                }

                if (! $this->twoFactorService->verifyToken($user, $data['token'])) {
                    return response()->json([
                        'message' => 'El código proporcionado es inválido',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $this->twoFactorService->activate($user, 'authenticator');
                $message = 'Doble factor por Google Authenticator habilitado';
            } elseif ($mode === 'email') {
                $this->twoFactorService->activarCorreo($user);
                $message = 'Doble factor por correo habilitado';
            } else {
                $this->twoFactorService->clear($user);
                $message = 'Doble factor deshabilitado';
            }

            if (isset($authUser->jti) && is_string($authUser->jti) && $authUser->jti !== '') {
                Cache::forget('user_by_jti:'.$authUser->jti);
            }

            return response()->json([
                'message' => $message,
                'data' => [
                    'tfa_activo' => (bool) ($user->{'tfa_activo'} ?? false),
                    '2fa_tipo' => $user->{'2fa_tipo'} ?? null,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Error alternando estado de 2FA desde cuenta', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al actualizar doble factor'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
