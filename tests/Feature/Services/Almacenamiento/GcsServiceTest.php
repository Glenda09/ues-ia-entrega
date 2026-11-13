<?php

namespace Tests\Feature\Services\Almacenamiento;

use App\Services\Almacenamiento\GcsService;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use Mockery;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

class GcsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Crea un servicio con un StorageClient simulado y un Bucket que existe.
     */
    protected function makeServiceWithMocks(?Bucket &$bucketMock = null, ?StorageClient &$clientMock = null): GcsService
    {
        // Configuración mínima requerida
        config([
            'services.gcs.bucket' => 'mi-bucket',
            'services.gcs.project_id' => null,
            'services.gcs.key_file' => null,
            'services.gcs.prefix' => null,
        ]);

        $bucketMock = Mockery::mock(Bucket::class);
        $bucketMock->shouldReceive('exists')->andReturn(true);

        $clientMock = Mockery::mock(StorageClient::class);
        $clientMock->shouldReceive('bucket')
            ->with('mi-bucket')
            ->andReturn($bucketMock);

        return new GcsService($clientMock);
    }

    public function test_subir_sube_archivo_y_retorna_nombre(): void
    {
        $service = $this->makeServiceWithMocks($bucket, $client);

        $objMock = Mockery::mock(StorageObject::class);
        $objMock->shouldReceive('name')
            ->once()
            ->andReturn('carpeta/archivo.txt');

        $bucket->shouldReceive('upload')
            ->once()
            ->with('contenido', Mockery::on(function ($opts) {
                return isset($opts['name']) && $opts['name'] === 'carpeta/archivo.txt';
            }))
            ->andReturn($objMock);

        $nombre = $service->subir('carpeta/archivo.txt', 'contenido');
        $this->assertSame('carpeta/archivo.txt', $nombre);
    }

    public function test_eliminar_borra_objeto(): void
    {
        $service = $this->makeServiceWithMocks($bucket, $client);

        $objMock = Mockery::mock(StorageObject::class);
        $objMock->shouldReceive('delete')->once();

        $bucket->shouldReceive('object')
            ->once()
            ->with('carpeta/archivo.txt')
            ->andReturn($objMock);

        $ok = $service->eliminar('carpeta/archivo.txt');
        $this->assertTrue($ok);
    }

    public function test_descargar_devuelve_contenido(): void
    {
        $service = $this->makeServiceWithMocks($bucket, $client);

        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->once()->andReturn('contenido');

        $objMock = Mockery::mock(StorageObject::class);
        $objMock->shouldReceive('downloadAsStream')->once()->andReturn($stream);

        $bucket->shouldReceive('object')
            ->once()
            ->with('carpeta/archivo.txt')
            ->andReturn($objMock);

        $contenido = $service->descargar('carpeta/archivo.txt');
        $this->assertSame('contenido', $contenido);
    }

    public function test_metadata_devuelve_array(): void
    {
        $service = $this->makeServiceWithMocks($bucket, $client);

        $objMock = Mockery::mock(StorageObject::class);
        $objMock->shouldReceive('info')->once()->andReturn([
            'name' => 'carpeta/archivo.txt',
            'size' => 5,
        ]);

        $bucket->shouldReceive('object')
            ->once()
            ->with('carpeta/archivo.txt')
            ->andReturn($objMock);

        $info = $service->metadata('carpeta/archivo.txt');
        $this->assertIsArray($info);
        $this->assertSame('carpeta/archivo.txt', $info['name']);
        $this->assertSame(5, $info['size']);
    }

    public function test_descargarMuchos_devuelve_mapa_con_nulos_en_errores(): void
    {
        $service = $this->makeServiceWithMocks($bucket, $client);

        // Para 'ok.txt'
        $streamOk = Mockery::mock(StreamInterface::class);
        $streamOk->shouldReceive('getContents')->andReturn('ok');
        $objOk = Mockery::mock(StorageObject::class);
        $objOk->shouldReceive('downloadAsStream')->andReturn($streamOk);

        // Para 'fail.txt' lanza excepción al descargar
        $objFail = Mockery::mock(StorageObject::class);
        $objFail->shouldReceive('downloadAsStream')->andThrow(new \RuntimeException('fallo'));

        $bucket->shouldReceive('object')
            ->andReturnUsing(function (string $name) use ($objOk, $objFail) {
                if ($name === 'ok.txt') {
                    return $objOk;
                }
                if ($name === 'fail.txt') {
                    return $objFail;
                }
                return Mockery::mock(StorageObject::class);
            });

        $mapa = $service->descargarMuchos(['ok.txt', 'fail.txt']);
        $this->assertSame('ok', $mapa['ok.txt']);
        $this->assertNull($mapa['fail.txt']);
    }
}

