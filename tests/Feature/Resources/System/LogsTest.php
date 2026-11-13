<?php

namespace Tests\Feature\Resources\System;

use App\Models\Auth\AuthApiKey;
use App\Models\Auth\AuthApiKeyPermiso;
use App\Models\Seguridad\SeguridadPermiso;
use App\Services\Audit\RequestLogRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestLogRepository::class)->truncate();
    }

    private function obtenerHeadersConApiKey(): array
    {
        $apiKeyValue = (string) Str::uuid();

        // Crear API Key para tests
        AuthApiKey::create([
            'cliente' => 'TEST_CLIENT',
            'api_key' => $apiKeyValue,
            'token_hash' => hash('sha256', $apiKeyValue),
            'id_estado' => 'ACTIVO',
            'created_at' => Carbon::now(),
        ]);

        SeguridadPermiso::firstOrCreate(
            ['permiso' => 'resources_request_logs'],
            ['descripcion' => 'Permiso de pruebas', 'activo' => true]
        );

        AuthApiKeyPermiso::create([
            'api_key' => $apiKeyValue,
            'id_permiso' => 'resources_request_logs',
            'creado_por' => null,
            'created_at' => Carbon::now(),
        ]);

        return [
            'X-API-Key' => $apiKeyValue,
            'Accept' => 'application/json',
        ];
    }

    private function crearLogsDeEjemplo(): void
    {
        // Log de hoy con POST
        app(RequestLogRepository::class)->create([
            'uuid' => '550e8400-e29b-41d4-a716-446655440001',
            'method' => 'POST',
            'uri' => '/api/auth/login',
            'query' => ['remember' => 'true'],
            'payload' => ['email' => 'user@example.com', 'password' => '***'],
            'response' => ['access_token' => 'token123', 'token_type' => 'bearer'],
            'status_code' => 200,
            'creation_date' => Carbon::now(),
            'updated_date' => Carbon::now(),
        ]);

        // Log de ayer con GET
        app(RequestLogRepository::class)->create([
            'uuid' => '550e8400-e29b-41d4-a716-446655440002',
            'method' => 'GET',
            'uri' => '/api/users/profile',
            'query' => ['include' => 'roles'],
            'payload' => [],
            'response' => ['id' => 1, 'username' => 'test_user'],
            'status_code' => 200,
            'creation_date' => Carbon::yesterday(),
            'updated_date' => Carbon::yesterday(),
        ]);

        // Log de hace 2 días con DELETE
        app(RequestLogRepository::class)->create([
            'uuid' => '550e8400-e29b-41d4-a716-446655440003',
            'method' => 'DELETE',
            'uri' => '/api/users/123',
            'query' => [],
            'payload' => [],
            'response' => ['message' => 'Usuario eliminado'],
            'status_code' => 200,
            'creation_date' => Carbon::now()->subDays(2),
            'updated_date' => Carbon::now()->subDays(2),
        ]);
    }

    public function test_consultar_logs_sin_filtros_devuelve_todos_ordenados_por_fecha_desc(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.request'), $headers);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_page',
                    'per_page',
                    'total',
                    'data' => [
                        '*' => [
                            'uuid',
                            'method',
                            'uri',
                            'query',
                            'payload',
                            'response',
                            'status_code',
                            'creation_date',
                            'updated_date',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Logs consultados exitosamente',
            ]);

        $data = $response->json('data.data');
        $this->assertCount(3, $data);

        // Verificar que están ordenados del más reciente al más antiguo
        $fechas = collect($data)->pluck('creation_date');
        $this->assertTrue($fechas->first() >= $fechas->last());
    }

    public function test_consultar_logs_filtro_por_uri_devuelve_resultados_correctos(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.request', ['uri' => 'auth']), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('auth', $data[0]['uri']);
    }

    public function test_consultar_logs_filtro_por_type_devuelve_resultados_correctos(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.request', ['type' => 'POST']), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('POST', $data[0]['method']);
    }

    public function test_consultar_logs_filtro_por_fecha_devuelve_resultados_correctos(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $fechaHoy = Carbon::now()->format('Y-m-d');
        $response = $this->getJson(route('resources.system.logs.request', ['creation_date' => $fechaHoy]), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');

        $fechaLog = Carbon::parse($data[0]['creation_date'])->format('Y-m-d');
        $this->assertEquals($fechaHoy, $fechaLog);
    }

    public function test_consultar_logs_ordenamiento_ascendente_funciona_correctamente(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.request', ['order' => 'asc']), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(3, $data);

        // Verificar que están ordenados del más antiguo al más reciente
        $fechas = collect($data)->pluck('creation_date');
        $this->assertTrue($fechas->first() <= $fechas->last());
    }

    public function test_consultar_logs_paginacion_funciona_correctamente(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.request', ['per_page' => 2]), $headers);

        $response->assertStatus(200);
    }

    public function test_consultar_logs_filtros_combinados_funcionan_correctamente(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $fechaHoy = Carbon::now()->format('Y-m-d');
        $response = $this->getJson(route('resources.system.logs.request', [
            'uri' => 'auth',
            'type' => 'POST',
            'creation_date' => $fechaHoy,
        ]), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('POST', $data[0]['method']);
        $this->assertStringContainsString('auth', $data[0]['uri']);
    }

    public function test_consultar_logs_parametros_invalidos_usa_valores_por_defecto(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.request', [
            'order' => 'invalid_order',
            'per_page' => 0,
        ]), $headers);

        $response->assertStatus(200);

        $data = $response->json('data');

        // Debe usar orden descendente por defecto
        $fechas = collect($data['data'])->pluck('creation_date');
        $this->assertTrue($fechas->first() >= $fechas->last());

        // Debe usar per_page = 1 (mínimo cuando se envía 0)
        $this->assertEquals(1, $data['per_page']);
    }

    public function test_consultar_logs_per_page_maximo_limitado_a_100(): void
    {
        $headers = $this->obtenerHeadersConApiKey();
        $response = $this->getJson(route('resources.system.logs.request', ['per_page' => 200]), $headers);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(100, $data['per_page']);
    }

    public function test_consultar_logs_sin_resultados_devuelve_array_vacio(): void
    {
        $headers = $this->obtenerHeadersConApiKey();
        $response = $this->getJson(route('resources.system.logs.request', ['uri' => 'nonexistent']), $headers);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logs consultados exitosamente',
            ]);

        $data = $response->json('data.data');
        $this->assertCount(0, $data);
    }

    public function test_consultar_logs_respuesta_cumple_estructura_documentacion(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.request'), $headers);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'data' => [
                        '*' => [
                            'uuid',
                            'method',
                            'uri',
                            'query',
                            'payload',
                            'response',
                            'status_code',
                            'creation_date',
                            'updated_date',
                        ],
                    ],
                ],
            ]);

        $responseData = $response->json();
        $this->assertIsBool($responseData['success']);
        $this->assertIsString($responseData['message']);
        $this->assertIsArray($responseData['data']['data']);
    }
}
