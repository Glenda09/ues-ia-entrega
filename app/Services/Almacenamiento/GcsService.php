<?php

namespace App\Services\Almacenamiento;

use DateTimeInterface;
use Google\Cloud\Core\Exception\ServiceException;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;

class GcsService
{
    protected string $bucketName;
    protected ?string $projectId;
    protected ?string $keyFilePath;
    protected ?string $prefix;
    protected ?string $publicBaseUrl;
    protected ?StorageClient $client;
    protected ?Bucket $bucketInstance = null;
    protected bool $bucketValidated = false;

    public function __construct($client = null)
    {
        $this->bucketName = (string) config('services.gcs.bucket');
        $this->projectId = config('services.gcs.project_id');
        $this->keyFilePath = config('services.gcs.key_file');
        $this->prefix = config('services.gcs.prefix');
        $this->publicBaseUrl = $this->sanitizePublicBaseUrl(config('services.gcs.public_base_url'));
        $this->client = $client instanceof StorageClient ? $client : null;
    }

    public function publicBaseUrl(): ?string
    {
        return $this->publicBaseUrl;
    }

    public function objectUrl(string $nombreObjeto, $expiracion = null, array $opciones = []): string
    {
        $mergedOptions = $this->withPublicBaseUrlOptions($opciones);

        return $this->signedUrl($nombreObjeto, $expiracion, $mergedOptions);
    }

    /**
     * Sube un archivo recibido (Illuminate\Http\UploadedFile) a GCS generando
     * un nombre legible dentro de una subruta opcional. Devuelve datos útiles.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string|null $subruta Carpeta lógica (p.ej. "uploads"), opcional
     * @return array{bucket:string,objeto:string,original:string,size:int,mime:string}
     */
    public function guardar($file, ?string $subruta = null): array
    {
        $bucketName = (string) $this->bucketName;
        if ($bucketName === '') {
            throw new \RuntimeException('Falta configurar GCS_BUCKET');
        }

        $subruta = trim((string) ($subruta ?? 'uploads'), '/');

        $ext = method_exists($file, 'getClientOriginalExtension') ? $file->getClientOriginalExtension() : '';
        if (method_exists($file, 'getMimeType')) {
            $detected = $file->getMimeType();
            $mime = $detected ?: 'application/octet-stream';
        } else {
            $mime = 'application/octet-stream';
        }
        $original = method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'archivo';
        $size = method_exists($file, 'getSize') ? (int) $file->getSize() : 0;

        $nombreObjeto = ($subruta ? $subruta . '/' : '')
            . now()->format('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8)
            . ($ext ? '.' . $ext : '');

        $stream = fopen($file->getRealPath(), 'r');

        $objetoFinal = $this->subir($nombreObjeto, $stream, [
            'metadata' => [
                'contentType' => $mime,
            ],
        ]);

        return [
            'bucket' => $bucketName,
            'objeto' => $objetoFinal,
            'original' => $original,
            'size' => $size,
            'mime' => $mime,
        ];
    }

    /**
     * Guarda contenido en GCS bajo el nombre indicado.
     *
     * @param string $nombreObjeto Nombre lógico del archivo (sin prefijo).
     * @param string|resource $contenido Contenido como string o stream.
     * @param array $opciones Opciones extra (por ejemplo, ['metadata' => [...]])
     * @return string Nombre final del objeto almacenado (con prefijo si aplica)
     */
    public function subir(string $nombreObjeto, $contenido, array $opciones = []): string
    {
        $bucket = $this->bucket();
        $ruta = $this->prefixedPath($nombreObjeto);

        $uploadOptions = ['name' => $ruta];
        if (isset($opciones['metadata']) && is_array($opciones['metadata'])) {
            $uploadOptions['metadata'] = $opciones['metadata'];
        }

        $obj = $bucket->upload($contenido, $uploadOptions);

        return $obj->name();
    }

    /**
     * Elimina un objeto del bucket.
     */
    public function eliminar(string $nombreObjeto): bool
    {
        $bucket = $this->bucket();
        $ruta = $this->prefixedPath($nombreObjeto);
        $obj = $bucket->object($ruta);
        $obj->delete();

        return true;
    }

    /**
     * Descarga el contenido de un objeto como string.
     */
    public function descargar(string $nombreObjeto): string
    {
        $bucket = $this->bucket();
        $ruta = $this->prefixedPath($nombreObjeto);
        $obj = $bucket->object($ruta);

        // downloadAsString está disponible en el cliente; usamos stream para compatibilidad.
        return $obj->downloadAsStream()->getContents();
    }

    /**
     * Obtiene la metadata (info) del objeto.
     *
     * @return array
     */
    public function metadata(string $nombreObjeto): array
    {
        $bucket = $this->bucket();
        $ruta = $this->prefixedPath($nombreObjeto);
        $obj = $bucket->object($ruta);

        return $obj->info();
    }

    /**
     * Descarga muchos objetos. Devuelve un arreglo asociativo nombre => contenido (o null si falla).
     *
     * @param array<int,string> $nombresObjetos
     * @return array<string,string|null>
     */
    public function descargarMuchos(array $nombresObjetos): array
    {
        $resultados = [];
        foreach ($nombresObjetos as $nombre) {
            try {
                $resultados[$nombre] = $this->descargar($nombre);
            } catch (\Throwable $e) {
                $resultados[$nombre] = null;
            }
        }

        return $resultados;
    }

    /**
     * Obtiene la instancia del bucket configurado.
     */
    protected function bucket(): Bucket
    {
        if ($this->bucketName === '') {
            throw new \RuntimeException('Falta configurar GCS_BUCKET');
        }

        $opciones = [];
        if (!empty($this->projectId)) {
            $opciones['projectId'] = $this->projectId;
        }
        $opciones = array_merge($opciones, $this->credentialOptions());

        $client = $this->client ?? new StorageClient($opciones);

        // Persistir el cliente para reutilizarlo en la misma instancia
        $this->client = $client;

        if ($this->bucketInstance instanceof Bucket) {
            return $this->bucketInstance;
        }

        $bucket = $client->bucket($this->bucketName);

        if (! $this->bucketValidated) {
            $this->assertBucketAccessible($bucket);
            $this->bucketValidated = true;
        }

        return $this->bucketInstance = $bucket;
    }

    /**
     * Aplica el prefijo configurado a la ruta del objeto.
     */
    protected function aplicarPrefijo(string $nombreObjeto): string
    {
        $pref = trim((string) $this->prefix, '/');
        $nombre = ltrim($nombreObjeto, '/');
        return $pref !== '' ? $pref . '/' . $nombre : $nombre;
    }

    public function prefixedPath(string $nombreObjeto): string
    {
        return $this->aplicarPrefijo($nombreObjeto);
    }

    protected function sanitizePublicBaseUrl($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return rtrim($trimmed, '/');
    }

    protected function withPublicBaseUrlOptions(array $options): array
    {
        if (! $this->publicBaseUrl) {
            return $options;
        }

        $parsed = parse_url($this->publicBaseUrl);
        if ($parsed === false || empty($parsed['host'])) {
            return $options;
        }

        $scheme = $parsed['scheme'] ?? 'https';

        return $options + [
            'scheme' => $scheme,
            'cname' => $this->publicBaseUrl,
        ];
    }

    /**
     * Determina las opciones de autenticación a utilizar con el cliente de GCS.
     *
     * @return array<string,mixed>
     */
    protected function credentialOptions(): array
    {
        $valor = is_string($this->keyFilePath) ? trim($this->keyFilePath) : '';
        if ($valor === '') {
            return [];
        }

        foreach ($this->candidateCredentialPaths($valor) as $ruta) {
            if ($ruta !== null && is_file($ruta) && is_readable($ruta)) {
                return ['keyFilePath' => $ruta];
            }
        }

        $credenciales = $this->decodeJsonCredentials($valor);
        if ($credenciales !== null) {
            return ['keyFile' => $credenciales];
        }

        throw new \RuntimeException("No se encontró el archivo de credenciales definido en GCS_KEY_FILE ({$valor}).");
    }

    /**
     * Genera las rutas potenciales donde podría estar el archivo de credenciales.
     *
     * @return array<int,string|null>
     */
    protected function candidateCredentialPaths(string $valor): array
    {
        $candidatos = [$valor];

        if (! $this->isAbsolutePath($valor) && function_exists('base_path')) {
            $candidatos[] = base_path($valor);
        }

        if (function_exists('base_path')) {
            $baseName = basename($valor);
            $candidatos[] = base_path($baseName);
        }

        if (function_exists('storage_path')) {
            $candidatos[] = storage_path($valor);
            $candidatos[] = storage_path('app/' . $valor);
            $candidatos[] = storage_path('app/' . basename($valor));
        }

        return array_unique(array_filter($candidatos));
    }

    /**
     * Intenta interpretar la configuración como JSON o JSON codificado en base64.
     *
     * @return array<string,mixed>|null
     */
    protected function decodeJsonCredentials(string $valor): ?array
    {
        $posibles = [$valor];
        $decodificado = base64_decode($valor, true);
        if ($decodificado !== false) {
            $posibles[] = $decodificado;
        }

        foreach ($posibles as $candidato) {
            $candidato = trim((string) $candidato);
            if ($candidato === '' || $candidato[0] !== '{') {
                continue;
            }

            try {
                $datos = json_decode($candidato, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                continue;
            }

            if (is_array($datos) && (isset($datos['private_key']) || isset($datos['client_email']))) {
                return $datos;
            }
        }

        return null;
    }

    /**
     * Determina si la ruta recibida es absoluta.
     */
    protected function isAbsolutePath(string $ruta): bool
    {
        if ($ruta === '') {
            return false;
        }

        if ($ruta[0] === DIRECTORY_SEPARATOR) {
            return true;
        }

        return (bool) preg_match('/^[a-zA-Z]:\\\\|^[a-zA-Z]:\//', $ruta);
    }

    /**
     * Valida si tenemos acceso al bucket o decide continuar si el permiso es insuficiente pero recuperable.
     */
    protected function assertBucketAccessible(Bucket $bucket): void
    {
        try {
            if (! $bucket->exists()) {
                throw new \RuntimeException("El bucket '{$this->bucketName}' no existe o no es accesible");
            }
        } catch (ServiceException $exception) {
            $codigo = $exception->getCode();
            $mensaje = $exception->getMessage();
            if (in_array($codigo, [401, 403], true) && str_contains($mensaje, 'storage.buckets.get')) {
                // La cuenta no tiene permiso para consultar metadata de bucket, pero sí podría acceder a los objetos.
                // Continuamos y dejaremos que las llamadas a objetos fallen si el bucket realmente no existe.
                return;
            }

            throw $exception;
        }
    }

    /**
     * Genera una URL firmada para un objeto almacenado en GCS.
     *
     * @param DateTimeInterface|int|null $expiracion Momento en que expira la URL o segundos relativos.
     * @param array<string,mixed> $opciones Opciones adicionales para la firma.
     */
    public function signedUrl(string $nombreObjeto, $expiracion = null, array $opciones = []): string
    {
        $bucket = $this->bucket();
        $ruta = $this->prefixedPath($nombreObjeto);
        $objeto = $bucket->object($ruta);

        if ($expiracion === null) {
            $expiracion = now()->addMinutes(10);
        } elseif (is_int($expiracion)) {
            $expiracion = now()->addSeconds($expiracion);
        }

        if (! $expiracion instanceof DateTimeInterface) {
            throw new \InvalidArgumentException('La expiración debe ser DateTimeInterface o segundos enteros.');
        }

        $opciones = ['version' => 'v4'] + $opciones;

        return $objeto->signedUrl($expiracion, $opciones);
    }
}
