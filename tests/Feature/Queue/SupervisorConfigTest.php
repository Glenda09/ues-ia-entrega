<?php

namespace Tests\Feature\Queue;

use Tests\TestCase;

class SupervisorConfigTest extends TestCase
{
    public function test_genera_el_programa_del_worker_configurado(): void
    {
        $archivo = tempnam(sys_get_temp_dir(), 'template-supervisor-');

        $this->assertNotFalse($archivo);

        try {
            $this->artisan('queue:supervisor-config', [
                '--target' => 'production',
                '--app-path' => '/var/www/html',
                '--php-bin' => '/usr/local/bin/php',
                '--user' => 'laravel',
                '--output' => $archivo,
            ])->assertExitCode(0);

            $configuracion = file_get_contents($archivo);

            $this->assertIsString($configuracion);
            $this->assertStringContainsString('[program:queue-default]', $configuracion);
            $this->assertStringContainsString('--queue=default,request-logs', $configuracion);
            $this->assertStringContainsString('numprocs=1', $configuracion);
            $this->assertStringContainsString('user=laravel', $configuracion);
        } finally {
            unlink($archivo);
        }
    }
}
