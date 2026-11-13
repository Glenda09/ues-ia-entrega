<?php

namespace App\Http\Middleware;

use App\Models\App\Estado;
use App\Models\Auth\AuthApiKey;
use App\Models\Auth\AuthApiKeyPermiso;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Valida la presencia de un API Key válido en la solicitud.
     * Acepta en cabecera `X-API-Key`. El valor recibido se hashea con SHA-256
     * y se busca por `token_hash`; el secreto bruto nunca se persiste.
     */
    public function handle(Request $request, Closure $next, ...$requiredPermissions)
    {
        $rawKey = $this->extractKey($request);

        if (! $rawKey) {
            return $this->unauthorized('API key no provisto');
        }

        $tokenHash = hash('sha256', $rawKey);
        $record = AuthApiKey::query()->where('token_hash', $tokenHash)->first();
        if (! $record) {
            return $this->unauthorized('API key inválido');
        }

        if (($record->id_estado ?? null) !== Estado::ACTIVO) {
            return $this->unauthorized('API key inactivo');
        }

        // Cargar permisos desde cache (TTL configurable)
        $cacheKey = 'ak_permissions_'.$record->api_key;
        $ttl = (int) config('auth.api_key_permissions_ttl', 300);
        $permisos = Cache::remember($cacheKey, $ttl, function () use ($record) {
            return AuthApiKeyPermiso::query()
                ->where('api_key', $record->api_key)
                ->pluck('id_permiso')
                ->unique()
                ->values()
                ->all();
        });

        // Exponer el registro en la request para uso posterior
        $request->attributes->set('auth_api_key', $record);
        $request->attributes->set('auth_permissions', $permisos);

        // Validar permisos si la ruta los requiere (permite acceso si tiene al menos uno o si posee 'root')
        if (! empty($requiredPermissions)) {
            if (in_array('root', $permisos, true)) {
                return $next($request);
            }
            $hasAny = ! empty(array_intersect($permisos, $requiredPermissions));
            if (! $hasAny) {
                return $this->forbidden('No tienes permisos para acceder a este recurso');
            }
        }

        return $next($request);
    }

    protected function extractKey(Request $request): ?string
    {
        $key = $request->header('X-API-Key');
        if (is_string($key) && $key !== '') {
            return trim($key);
        }

        return null;
    }

    protected function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }

    protected function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_FORBIDDEN);
    }
}
