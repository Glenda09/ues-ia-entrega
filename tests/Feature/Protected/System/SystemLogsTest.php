<?php

namespace Tests\Feature\Protected\System;

use App\Services\Audit\AuditJobsRepository;
use App\Services\Audit\SessionAuditRepository;
use App\Services\Audit\SystemLogRepository;
use Carbon\Carbon;
use Tests\TestCase;

class SystemLogsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();


        app(SystemLogRepository::class)->truncate();
        app(SessionAuditRepository::class)->truncate();
    }

    protected function tearDown(): void
    {
        app(AuditJobsRepository::class)->truncate();

        parent::tearDown();
    }

    private function authHeaders(): array
    {
        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => 'admin@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        return ['Authorization' => 'Bearer ' . $login['access_token']];
    }

    private function crearSystemLogsDeEjemplo(): void
    {
        app(SystemLogRepository::class)->create([
            'uuid' => 'bb0b9870-0b39-4e33-9fb3-1fa1ba6f0001',
            'message' => 'Job procesado',
            'context' => ['job' => 'SyncReports'],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'queue',
            'datetime' => Carbon::now(),
            'extra' => [],
        ]);

        app(SystemLogRepository::class)->create([
            'uuid' => 'bb0b9870-0b39-4e33-9fb3-1fa1ba6f0002',
            'message' => 'Notificación enviada',
            'context' => ['user_id' => 12],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'notifications',
            'datetime' => Carbon::yesterday(),
            'extra' => [],
        ]);

        app(SystemLogRepository::class)->create([
            'uuid' => 'bb0b9870-0b39-4e33-9fb3-1fa1ba6f0003',
            'message' => 'Error al procesar',
            'context' => ['exception' => 'RuntimeException'],
            'level' => 400,
            'level_name' => 'ERROR',
            'channel' => 'queue',
            'datetime' => Carbon::now()->subDays(2),
            'extra' => [],
        ]);
    }

    public function test_consultar_system_logs_devuelve_listado_paginado(): void
    {
        $this->crearSystemLogsDeEjemplo();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(route('protected.system.logs.system'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logs consultados exitosamente',
            ])
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
            ]);

        $data = $response->json('data.data');
        $this->assertCount(3, $data);

        $fechas = collect($data)->pluck('datetime');
        $this->assertTrue($fechas->first() >= $fechas->last());
    }

    public function test_consultar_system_logs_filtra_por_canal(): void
    {
        $this->crearSystemLogsDeEjemplo();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(
            route('protected.system.logs.system', ['channel' => 'notifications'])
        );

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('notifications', $data[0]['channel']);
    }

    public function test_consultar_system_logs_sin_token_retornara_401(): void
    {
        $response = $this->getJson(route('protected.system.logs.system'));

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }
}
