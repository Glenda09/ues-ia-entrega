<?php

namespace Tests\Feature\Protected\Seguridad;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class ModuloTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_module_index_returns_seeded_modules(): void
    {
        $this->withoutMiddleware();

        $response = $this->getJson(route('protected.seguridad.modulo.index'));

        $response->assertOk();

        $payload = $response->json();

        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);

        $root = collect($payload)->firstWhere('codigo', 'ROOT');

        $this->assertNotNull($root, 'El módulo ROOT debe estar disponible en el listado.');
        $this->assertSame('Root', $root['nombre']);
        $this->assertSame('ACTIVO', $root['id_estado']);
    }
}
