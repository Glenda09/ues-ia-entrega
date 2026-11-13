<?php

namespace Tests\Feature\Protected\System;

use App\Models\Auth\AuthUsuario;
use App\Services\Audit\RequestLogRepository;
use App\Services\Audit\SessionAuditRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RequestLogsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();


        app(RequestLogRepository::class)->truncate();
        app(SessionAuditRepository::class)->truncate();
    }

    private function authHeaders(): array
    {
        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => 'admin@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        return ['Authorization' => 'Bearer ' . $login['access_token']];
    }

    private function crearLogsDeEjemplo(): void
    {
        app(RequestLogRepository::class)->create([
            'uuid' => 'aa0b9870-0b39-4e33-9fb3-1fa1ba6f0001',
            'method' => 'POST',
            'uri' => '/api/auth/login',
            'query' => ['remember' => '1'],
            'payload' => ['email' => 'admin@example.com'],
            'response' => ['message' => 'ok'],
            'status_code' => 200,
            'creation_date' => Carbon::now(),
            'updated_date' => Carbon::now(),
        ]);

        app(RequestLogRepository::class)->create([
            'uuid' => 'aa0b9870-0b39-4e33-9fb3-1fa1ba6f0002',
            'method' => 'GET',
            'uri' => '/api/users',
            'query' => ['page' => '1'],
            'payload' => [],
            'response' => ['data' => []],
            'status_code' => 200,
            'creation_date' => Carbon::yesterday(),
            'updated_date' => Carbon::yesterday(),
        ]);

        app(RequestLogRepository::class)->create([
            'uuid' => 'aa0b9870-0b39-4e33-9fb3-1fa1ba6f0003',
            'method' => 'DELETE',
            'uri' => '/api/users/11',
            'query' => [],
            'payload' => [],
            'response' => ['message' => 'deleted'],
            'status_code' => 200,
            'creation_date' => Carbon::now()->subDays(2),
            'updated_date' => Carbon::now()->subDays(2),
        ]);
    }

    public function test_consultar_request_logs_devuelve_listado_paginado(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->authHeaders();

        $response = $this->withHeaders($headers)->getJson(route('protected.system.logs.request'));

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
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Logs consultados exitosamente',
            ]);

        $data = $response->json('data.data');
        $this->assertCount(3, $data);

        $fechas = collect($data)->pluck('creation_date');
        $this->assertTrue($fechas->first() >= $fechas->last());
    }

    public function test_consultar_request_logs_aplica_filtros_correctamente(): void
    {
        $this->crearLogsDeEjemplo();
        $headers = $this->authHeaders();

        $fechaHoy = Carbon::now()->format('Y-m-d');
        $response = $this->withHeaders($headers)->getJson(
            route('protected.system.logs.request', [
                'uri' => 'auth',
                'type' => 'post',
                'creation_date' => $fechaHoy,
            ])
        );

        $response->assertStatus(200);

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('POST', $data[0]['method']);
        $this->assertStringContainsString('auth', $data[0]['uri']);
    }

    public function test_consultar_request_logs_sin_token_retornara_401(): void
    {
        $response = $this->getJson(route('protected.system.logs.request'));

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_consultar_request_logs_sin_permiso_retornara_403(): void
    {
        AuthUsuario::create([
            'username' => 'usuario_sin_permisos',
            'email' => 'usuario_sin_permisos@example.com',
            'password' => Hash::make('password123'),
            'id_estado' => 'ACTIVO',
        ]);

        app(SessionAuditRepository::class)->truncate();

        $login = $this->postJson(route('public.auth.login'), [
            'identifier' => 'usuario_sin_permisos@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        $headers = ['Authorization' => 'Bearer ' . $login['access_token']];

        $response = $this->withHeaders($headers)->getJson(route('protected.system.logs.request'));

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'No tienes permisos para acceder a este recurso.',
            ]);
    }
}
