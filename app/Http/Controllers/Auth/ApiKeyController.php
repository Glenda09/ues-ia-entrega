<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\App\Estado;
use App\Models\Auth\AuthApiKey;
use App\Models\Auth\AuthApiKeyPermiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'cliente' => 'nullable|string|max:255',
                'estado' => 'nullable|string|exists:estado,codigo',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $perPage = $validated['per_page'] ?? 15;

            $query = AuthApiKey::query()->with(['permisos:permiso'])->orderByDesc('created_at');

            if (! empty($validated['cliente'])) {
                $cliente = strtolower($validated['cliente']);
                $query->whereRaw('LOWER(cliente) LIKE ?', ['%'.$cliente.'%']);
            }

            if (! empty($validated['estado'])) {
                $query->where('id_estado', $validated['estado']);
            }

            $paginator = $query->paginate($perPage);

            $data = $paginator->getCollection()->map(function ($k) {
                return [
                    'api_key' => $k->api_key,
                    'cliente' => $k->cliente,
                    'id_estado' => $k->id_estado,
                    'created_at' => $k->created_at,
                    'permisos' => $k->permisos?->pluck('permiso')->values()->all() ?? [],
                ];
            });

            return response()->json([
                'data' => $data,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Error listando API keys', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al listar API keys'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crea una nueva API Key.
     *
     * `api_key` es el identificador público (UUID); `secret` es el valor bruto
     * de 256 bits que el cliente debe enviar en `X-API-Key`. Solo su hash
     * SHA-256 (`token_hash`) se persiste, por lo que `secret` se devuelve una
     * única vez en esta respuesta y no puede recuperarse después.
     */
    public function guardar(Request $request)
    {
        try {
            $validated = $request->validate([
                'cliente' => 'required|string|max:255',
                'id_estado' => 'nullable|string|exists:estado,codigo',
                'permisos' => 'sometimes|array',
                'permisos.*' => 'string|exists:seguridad_permiso,permiso',
            ]);

            $apiKey = (string) Str::uuid();
            $secret = bin2hex(random_bytes(32));

            $record = AuthApiKey::create([
                'api_key' => $apiKey,
                'token_hash' => hash('sha256', $secret),
                'cliente' => $validated['cliente'],
                'id_estado' => $validated['id_estado'] ?? Estado::ACTIVO,
                'created_at' => now(),
            ]);

            $permisos = $validated['permisos'] ?? [];
            if (! empty($permisos)) {
                $now = now();
                foreach ($permisos as $permiso) {
                    AuthApiKeyPermiso::updateOrCreate(
                        [
                            'api_key' => $record->api_key,
                            'id_permiso' => $permiso,
                        ],
                        [
                            'creado_por' => $request->attributes->get('auth_user')?->id_usuario,
                            'created_at' => $now,
                        ]
                    );
                }
            }
            // Limpiar cache de permisos por si existiera
            Cache::forget('ak_permissions_'.$record->api_key);

            return response()->json([
                'message' => 'API key creada correctamente',
                'data' => [
                    'api_key' => $record->api_key,
                    'secret' => $secret,
                ],
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Error creando API key', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al crear API key'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function estado(string $api_key)
    {
        $record = AuthApiKey::find($api_key);
        if (! $record) {
            return response()->json(['message' => 'API key no encontrada'], Response::HTTP_NOT_FOUND);
        }

        $record->id_estado = $record->id_estado === Estado::ACTIVO ? Estado::INACTIVO : Estado::ACTIVO;
        $record->save();

        // Limpiar cache de permisos al cambiar estado
        Cache::forget('ak_permissions_'.$record->api_key);

        return response()->json(['message' => 'Estado de API key actualizado correctamente']);
    }

    public function sincronizarPermisos(Request $request, string $api_key)
    {
        try {
            $validated = $request->validate([
                'permisos' => 'required|array',
                'permisos.*' => 'string|exists:seguridad_permiso,permiso',
            ]);

            $record = AuthApiKey::find($api_key);
            if (! $record) {
                return response()->json(['message' => 'API key no encontrada'], Response::HTTP_NOT_FOUND);
            }

            $nuevos = collect($validated['permisos'])->map(fn ($p) => (string) $p)->unique()->values();
            $actuales = AuthApiKeyPermiso::query()
                ->where('api_key', $api_key)
                ->pluck('id_permiso');

            $toAdd = $nuevos->diff($actuales);
            $toRemove = $actuales->diff($nuevos);

            $now = now();
            foreach ($toAdd as $permiso) {
                AuthApiKeyPermiso::updateOrCreate(
                    [
                        'api_key' => $api_key,
                        'id_permiso' => $permiso,
                    ],
                    [
                        'creado_por' => $request->attributes->get('auth_user')?->id_usuario,
                        'created_at' => $now,
                    ]
                );
            }

            if ($toRemove->isNotEmpty()) {
                AuthApiKeyPermiso::query()
                    ->where('api_key', $api_key)
                    ->whereIn('id_permiso', $toRemove->all())
                    ->delete();
            }

            // Limpiar cache de permisos tras sincronización
            Cache::forget('ak_permissions_'.$api_key);

            return response()->json([
                'message' => 'Permisos sincronizados correctamente',
                'data' => [
                    'api_key' => $api_key,
                    'permisos' => $nuevos->values()->all(),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Error sincronizando permisos de API key', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al sincronizar permisos'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
