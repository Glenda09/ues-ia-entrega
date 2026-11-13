<?php

namespace Tests\Feature\Resources\System;

use App\Models\Auth\AuthApiKey;
use App\Models\Auth\AuthApiKeyPermiso;
use App\Models\Seguridad\SeguridadPermiso;
use App\Services\Audit\AuditJobsRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class JobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

    }

    protected function tearDown(): void
    {
        app(AuditJobsRepository::class)->truncate();

        parent::tearDown();
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
            ['permiso' => 'resources_jobs'],
            ['descripcion' => 'Permiso de pruebas', 'activo' => true]
        );

        AuthApiKeyPermiso::create([
            'api_key' => $apiKeyValue,
            'id_permiso' => 'resources_jobs',
            'creado_por' => null,
            'created_at' => Carbon::now(),
        ]);

        return [
            'X-API-Key' => $apiKeyValue,
            'Accept' => 'application/json',
        ];
    }

    private function crearJobsDeEjemplo(): void
    {
        // Job de hoy en cola "emails"
        app(AuditJobsRepository::class)->create([
            'queue' => 'emails',
            'created_at' => Carbon::now(),
        ]);

        // Job de ayer en cola "reports"
        app(AuditJobsRepository::class)->create([
            'queue' => 'reports',
            'created_at' => Carbon::yesterday(),
        ]);

        // Job de hace 2 días en cola "emails"
        app(AuditJobsRepository::class)->create([
            'queue' => 'emails',
            'created_at' => Carbon::now()->subDays(2),
        ]);
    }

    public function test_consultar_jobs_sin_filtros_devuelve_todos_ordenados_por_fecha_desc(): void
    {
        $this->crearJobsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.jobs'), $headers);

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
                            'queue',
                            'created_at',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Jobs consultados exitosamente',
            ]);

        $data = $response->json('data.data');
        $this->assertCount(3, $data);

        // Verificar que están ordenados del más reciente al más antiguo
        $fechas = collect($data)->pluck('created_at');
        $this->assertTrue($fechas->first() >= $fechas->last());
    }

    public function test_consultar_jobs_filtro_por_queue_devuelve_resultados_correctos(): void
    {
        $this->crearJobsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.jobs', ['queue' => 'emails']), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(2, $data);
        $this->assertEquals('emails', $data[0]['queue']);
    }

    public function test_consultar_jobs_filtro_por_fecha_devuelve_resultados_correctos(): void
    {
        $this->crearJobsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $fechaHoy = Carbon::now()->format('Y-m-d');
        $response = $this->getJson(route('resources.system.logs.jobs', ['created_at' => $fechaHoy]), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);

        $fechaJob = Carbon::parse($data[0]['created_at'])->format('Y-m-d');
        $this->assertEquals($fechaHoy, $fechaJob);
    }

    public function test_consultar_jobs_ordenamiento_ascendente_funciona_correctamente(): void
    {
        $this->crearJobsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.jobs', ['order' => 'asc']), $headers);

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(3, $data);

        // Verificar que están ordenados del más antiguo al más reciente
        $fechas = collect($data)->pluck('created_at');
        $this->assertTrue($fechas->first() <= $fechas->last());
    }

    public function test_consultar_jobs_paginacion_funciona_correctamente(): void
    {
        $this->crearJobsDeEjemplo();
        $headers = $this->obtenerHeadersConApiKey();

        $response = $this->getJson(route('resources.system.logs.jobs', ['per_page' => 2]), $headers);

        $response->assertStatus(200);

        $paginationData = $response->json('data');
        $this->assertEquals(2, $paginationData['per_page']);
        $this->assertEquals(3, $paginationData['total']);
        $this->assertEquals(2, $paginationData['last_page']);
        $this->assertCount(2, $paginationData['data']);
    }
}
