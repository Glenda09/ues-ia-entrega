<?php

namespace App\Http\Middleware;

use App\Models\Auth\AuthUsuario;
use App\Services\Auth\PermisosEfectivosService;
use App\Services\Auth\TwoFactorService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public const RUTAS_CONFIGURACION_2FA = ['protected.cuenta.info', 'protected.cuenta.2fa', 'protected.cuenta.2fa-setup', 'protected.auth.logout'];

    /** EST-01: rutas accesibles mientras el cambio de contraseña inicial está pendiente. */
    public const RUTAS_CAMBIO_INICIAL = ['protected.cuenta.info', 'protected.auth.change-password', 'protected.auth.logout', 'protected.cuenta.2fa', 'protected.cuenta.2fa-setup'];

    protected array $snapshot = [];

    public function __construct(private PermisosEfectivosService $permisosEfectivosService, private TwoFactorService $twoFactorService) {}

    /**
     * Valida el token JWT y autoriza por permisos.
     * Parámetros opcionales del middleware son los permisos requeridos para la ruta.
     */
    public function handle(Request $request, Closure $next, ...$requiredPermissions)
    {
        // Evita doble parsing si el middleware se aplica a nivel de grupo y de ruta
        $user = $request->attributes->get('auth_user');
        $userPermissions = $request->attributes->get('auth_permissions');

        if (! $user) {
            try {
                $payload = JWTAuth::parseToken()->getPayload();
                $userId = (string) ($payload->get('sub'));
                if (! $userId) {
                    return $this->unauthorized('Token inválido.');
                }
                $user = (object) ['id_usuario' => $userId, 'jti' => $payload->get('jti')];
            } catch (TokenExpiredException $e) {
                return $this->unauthorized('Token expirado');
            } catch (TokenInvalidException $e) {
                return $this->unauthorized('Token inválido');
            } catch (JWTException $e) {
                return $this->unauthorized('Token no provisto');
            }
        }

        $cuenta = AuthUsuario::find($user->id_usuario);
        if (! $cuenta) {
            return $this->unauthorized('Usuario no autorizado');
        }
        if ($this->twoFactorService->configuracionPendiente($cuenta) && ! in_array($request->route()?->getName(), self::RUTAS_CONFIGURACION_2FA, true)) {
            return response()->json([
                'message' => 'Debes configurar el doble factor de autenticación para continuar',
                'code' => 'DOBLE_FACTOR_CONFIGURACION_PENDIENTE',
            ], Response::HTTP_FORBIDDEN);
        }

        // EST-01: primero 2FA (arriba); luego cambio de contraseña inicial.
        if ($cuenta->password_cambio_requerido && ! in_array($request->route()?->getName(), self::RUTAS_CAMBIO_INICIAL, true)) {
            $vencida = $cuenta->password_temporal_expira_en && $cuenta->password_temporal_expira_en->isPast();

            return response()->json([
                'message' => $vencida ? 'Tu contraseña temporal venció. Usa la recuperación de cuenta.' : 'Debes cambiar tu contraseña inicial para continuar',
                'code' => $vencida ? 'ACCESO_TEMPORAL_VENCIDO' : 'CAMBIO_PASSWORD_INICIAL_PENDIENTE',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! is_array($userPermissions)) {
            $userPermissions = $this->permisosEfectivosService->paraUsuario($user->id_usuario);
        }

        // Exponer en la request por si otro middleware/controlador lo necesita
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_permissions', $userPermissions);

        // Si tiene permiso root, pasa sin más validación
        if (in_array('root', $userPermissions, true)) {
            return $next($request);
        }

        // Si la ruta especifica permisos, validar acceso
        if (! empty($requiredPermissions)) {
            // Validación permisiva: basta con que el usuario tenga ALGUNO de los permisos requeridos
            $hasAny = ! empty(array_intersect($userPermissions, $requiredPermissions));
            if (! $hasAny) {
                return $this->forbidden('No tienes permisos para acceder a este recurso.', [
                    'required' => array_values($requiredPermissions),
                ]);
            }
        }

        return $next($request);
    }

    protected function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }

    protected function forbidden(string $message, array $meta = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'meta' => $meta,
        ], Response::HTTP_FORBIDDEN);
    }
}
