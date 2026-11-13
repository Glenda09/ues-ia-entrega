<?php

namespace Tests\Feature\Protected\System;

use App\Services\Audit\AuditJobsRepository;
use App\Services\Audit\SessionAuditRepository;
use Carbon\Carbon;
use Tests\TestCase;

class ProtectedJobsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AuditJobsRepository::class)->truncate();
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

    private function crearJobsDeEjemplo(): void
    {
        app(AuditJobsRepository::class)->create([
            'queue' => 'emails',
            'created_at' => Carbon::now(),
        ]);

        app(AuditJobsRepository::class)->create([
            'queue' => 'reports',
            'created_at' => Carbon::yesterday(),
        ]);

        app(AuditJobsRepository::class)->create([
            'queue' => 'emails',
            'created_at' => Carbon::now()->subDays(2),
        ]);
    }

    public function test_consultar_jobs_devuelve_listado_paginado(): void
    {
        $this->crearJobsDeEjemplo();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(route('protected.system.logs.jobs'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Jobs consultados exitosamente',
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
                            'queue',
                            'created_at',
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data.data');
        $this->assertCount(3, $data);

        $fechas = collect($data)->pluck('created_at');
        $this->assertTrue($fechas->first() >= $fechas->last());
    }

    public function test_consultar_jobs_filtra_por_queue(): void
    {
        $this->crearJobsDeEjemplo();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(
            route('protected.system.logs.jobs', ['queue' => 'emails'])
        );

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(2, $data);
        $this->assertEquals('emails', $data[0]['queue']);
    }

    public function test_consultar_jobs_sin_token_retornara_401(): void
    {
        $response = $this->getJson(route('protected.system.logs.jobs'));

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }
}
