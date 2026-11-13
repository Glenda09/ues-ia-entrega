<?php

namespace Tests\Feature\Resources\System;

use App\Models\Auth\AuthApiKey;
use App\Models\Auth\AuthApiKeyPermiso;
use App\Models\Seguridad\SeguridadPermiso;
use App\Services\Audit\SystemLogRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SystemLogRepository::class)->truncate();
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
            ['permiso' => 'resources_system_logs'],
            ['descripcion' => 'Permiso de pruebas', 'activo' => true]
        );

        AuthApiKeyPermiso::create([
            'api_key' => $apiKeyValue,
            'id_permiso' => 'resources_system_logs',
            'creado_por' => null,
            'created_at' => Carbon::now(),
        ]);

        return [
            'X-API-Key' => $apiKeyValue,
            'Accept' => 'application/json',
        ];
    }

    private function crearSystemLogsDeEjemplo(): void
    {
        // Log de hoy, nivel 200, canal "api"
        app(SystemLogRepository::class)->create([
            'uuid' => '660e8400-e29b-41d4-a716-446655440001',
            'message' => 'User logged in',
            'context' => ['user_id' => 1],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'api',
            'datetime' => Carbon::now(),
            'extra' => [],
        ]);

        // Log de ayer, nivel 300, canal "queue"
        app(SystemLogRepository::class)->create([
            'uuid' => '660e8400-e29b-41d4-a716-446655440002',
            'message' => 'Job processed in test',
            'context' => ['job' => 'SendEmail'],
            'level' => 300,
            'level_name' => 'WARNING',
            'channel' => 'queue',
            'datetime' => Carbon::yesterday(),
            'extra' => [],
        ]);

        // Log de hace 2 días, nivel 400, canal "api"
        app(SystemLogRepository::class)->create([
            'uuid' => '660e8400-e29b-41d4-a716-446655440003',
            'message' => 'Error saving data',
            'context' => ['model' => 'User'],
            'level' => 400,
            'level_name' => 'ERROR',
            'channel' => 'api',
            'datetime' => Carbon::now()->subDays(2),
            'extra' => [],
        ]);
    }

    public function test_consultar_system_logs_sin_filtros_devuelve_todos_ordenados_por_fecha_desc(): void
    {
        $this->crearSystemLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.system'), $headers);

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
                            'message',
                            'context',
                            'level',
                            'level_name',
                            'channel',
                            'datetime',
                            'extra',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Logs consultados exitosamente',
            ]);

        $data = $response->json('data.data');

        // Verificar que están ordenados del más reciente al más antiguo
        $fechas = collect($data)->pluck('datetime');
        $this->assertTrue($fechas->first() >= $fechas->last());
    }

    public function test_consultar_system_logs_filtro_por_level_devuelve_resultados_correctos(): void
    {
        $this->crearSystemLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.system', ['level' => 200]), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals(200, $data[0]['level']);
    }

    public function test_consultar_system_logs_filtro_por_channel_devuelve_resultados_correctos(): void
    {
        $this->crearSystemLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.system', ['channel' => 'queue']), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('queue', $data[0]['channel']);
    }

    public function test_consultar_system_logs_filtro_por_fecha_devuelve_resultados_correctos(): void
    {
        $this->crearSystemLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $fechaHoy = Carbon::now()->format('Y-m-d');
        $response = $this->getJson(route('resources.system.logs.system', ['datetime' => $fechaHoy]), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);

        $fechaLog = Carbon::parse($data[0]['datetime'])->format('Y-m-d');
        $this->assertEquals($fechaHoy, $fechaLog);
    }

    public function test_consultar_system_logs_ordenamiento_ascendente_funciona_correctamente(): void
    {
        $this->crearSystemLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.system', ['order' => 'asc']), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(3, $data);

        // Verificar que están ordenados del más antiguo al más reciente
        $fechas = collect($data)->pluck('datetime');
        $this->assertTrue($fechas->first() <= $fechas->last());
    }

    public function test_consultar_system_logs_paginacion_funciona_correctamente(): void
    {
        $this->crearSystemLogsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.system', ['per_page' => 2]), $headers);

        $response->assertStatus(200);

        $paginationData = $response->json('data');
        $this->assertEquals(2, $paginationData['per_page']);
        $this->assertEquals(3, $paginationData['total']);
        $this->assertEquals(2, $paginationData['last_page']);
        $this->assertCount(2, $paginationData['data']);
    }
}
