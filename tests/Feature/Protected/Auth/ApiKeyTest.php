<?php

namespace Tests\Feature\Protected\Auth;

use App\Models\App\Estado;
use App\Models\Auth\AuthApiKey;
use App\Models\Auth\AuthApiKeyPermiso;
use App\Models\Auth\AuthUsuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    private function authHeaders(): array
    {
        $admin = AuthUsuario::where('email', 'admin@example.com')->first();
        if (!$admin) {
            // fallback in case seeder changes
            $admin = AuthUsuario::factory()->create([
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
            ]);
        }

        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => $admin->email,
            'password' => 'password123',
        ])->json();

        return ['Authorization' => 'Bearer ' . $login['access_token']];
    }

    public function test_index_api_keys(): void
    {
        // Crear datos
        $keyA = AuthApiKey::create([
            'api_key' => (string) Str::uuid(),
            'cliente' => 'Cliente A',
            'id_estado' => Estado::ACTIVO,
            'created_at' => now(),
        ]);
        $keyB = AuthApiKey::create([
            'api_key' => (string) Str::uuid(),
            'cliente' => 'Cliente B',
            'id_estado' => Estado::INACTIVO,
            'created_at' => now(),
        ]);

        // Asignar permisos a Cliente A
        AuthApiKeyPermiso::create([
            'api_key' => $keyA->api_key,
            'id_permiso' => 'auth_api_key_index',
            'created_at' => now(),
        ]);
        AuthApiKeyPermiso::create([
            'api_key' => $keyA->api_key,
            'id_permiso' => 'seguridad_permisos_index',
            'created_at' => now(),
        ]);

        $headers = $this->authHeaders();

        // sin filtros
        $resp = $this->withHeaders($headers)->getJson(route('protected.auth.api-key.index'));
        $resp->assertStatus(200)->assertJsonStructure(['data', 'pagination']);

        // verificar que incluye permisos
        $data = collect($resp->json('data'));
        $itemA = $data->firstWhere('api_key', $keyA->api_key);
        $this->assertIsArray($itemA['permisos'] ?? null);
        $this->assertTrue(in_array('auth_api_key_index', $itemA['permisos'], true));
        $this->assertTrue(in_array('seguridad_permisos_index', $itemA['permisos'], true));

        // con filtro por estado
        $resp = $this->withHeaders($headers)->getJson(route('protected.auth.api-key.index', ['estado' => Estado::ACTIVO]));
        $resp->assertStatus(200)->assertJsonStructure(['data', 'pagination']);
    }

    public function test_guardar_api_key(): void
    {
        $headers = $this->authHeaders();

        $payload = [
            'cliente' => 'Cliente Nuevo',
            'permisos' => ['auth_api_key_index', 'seguridad_permisos_index'],
        ];

        $resp = $this->withHeaders($headers)->postJson(route('protected.auth.api-key.guardar'), $payload);
        $resp->assertStatus(201)->assertJsonStructure(['message', 'data' => ['api_key']]);

        $apiKey = $resp->json('data.api_key');
        $this->assertNotEmpty($apiKey);
        $this->assertDatabaseHas('auth_api_key', ['api_key' => $apiKey, 'cliente' => 'Cliente Nuevo']);
        $this->assertDatabaseHas('auth_api_key_permiso', ['api_key' => $apiKey, 'id_permiso' => 'auth_api_key_index']);
        $this->assertDatabaseHas('auth_api_key_permiso', ['api_key' => $apiKey, 'id_permiso' => 'seguridad_permisos_index']);
    }

    public function test_estado_api_key_toggle(): void
    {
        $headers = $this->authHeaders();

        $key = AuthApiKey::create([
            'api_key' => (string) Str::uuid(),
            'cliente' => 'Cliente Toggle',
            'id_estado' => Estado::INACTIVO,
            'created_at' => now(),
        ]);

        // activar
        $resp = $this->withHeaders($headers)->putJson(route('protected.auth.api-key.estado', $key->api_key));
        $resp->assertStatus(200)->assertJson(['message' => 'Estado de API key actualizado correctamente']);
        $this->assertDatabaseHas('auth_api_key', ['api_key' => $key->api_key, 'id_estado' => Estado::ACTIVO]);

        // desactivar
        $resp = $this->withHeaders($headers)->putJson(route('protected.auth.api-key.estado', $key->api_key));
        $resp->assertStatus(200)->assertJson(['message' => 'Estado de API key actualizado correctamente']);
        $this->assertDatabaseHas('auth_api_key', ['api_key' => $key->api_key, 'id_estado' => Estado::INACTIVO]);
    }

    public function test_sincronizar_permisos_api_key(): void
    {
        $headers = $this->authHeaders();

        $key = AuthApiKey::create([
            'api_key' => (string) Str::uuid(),
            'cliente' => 'Cliente Permisos',
            'id_estado' => Estado::ACTIVO,
            'created_at' => now(),
        ]);

        // permisos iniciales
        AuthApiKeyPermiso::create([
            'api_key' => $key->api_key,
            'id_permiso' => 'auth_api_key_index',
            'created_at' => now(),
        ]);

        // sincronizar a nuevo set (agrega y remueve)
        $payload = [
            'permisos' => ['seguridad_permisos_index', 'auth_api_key_guardar'],
        ];
        $resp = $this->withHeaders($headers)->putJson(route('protected.auth.api-key.permisos-sincronizar', $key->api_key), $payload);
        $resp->assertStatus(200)->assertJson(['message' => 'Permisos sincronizados correctamente']);

        // assert insertados
        $this->assertDatabaseHas('auth_api_key_permiso', ['api_key' => $key->api_key, 'id_permiso' => 'seguridad_permisos_index']);
        $this->assertDatabaseHas('auth_api_key_permiso', ['api_key' => $key->api_key, 'id_permiso' => 'auth_api_key_guardar']);
        // assert removido el anterior
        $this->assertDatabaseMissing('auth_api_key_permiso', ['api_key' => $key->api_key, 'id_permiso' => 'auth_api_key_index']);
    }
}
