<?php

namespace App\Http\Middleware;

use App\Services\Audit\RequestLogRepository;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RequestLoggerMiddleware
{
    protected array $snapshot = [];

    public function __construct(private RequestLogRepository $requestLogRepository) {}

    public function handle($request, Closure $next, ...$params)
    {
        $request->attributes->set('log.request:response_excludes', $params);

        return $next($request);
    }

    public function terminate($request, $response): void
    {
        $scope = explode('/', $request->path());
        $logType = $scope[0];

        $headers = $request->header();
        unset($headers['cookie']);

        if (isset($headers['authorization'])) {
            $headers['authorization'] = '***';
        }

        if (isset($headers['x-api-key'])) {
            $apiKey = $headers['x-api-key'][0];
            $length = strlen($apiKey);
            $headers['x-api-key'] = [substr($apiKey, $length - 4, $length)];
        }

        $this->snapshot = [
            'uuid' => (string) Str::uuid(),
            'method' => $request->method(),
            'uri' => $request->path(),
            'query' => $request->query(),
            'payload' => $request->except(['password', 'access_token', 'refresh_token']),
            'headers' => $headers,
            'response' => null,
            'type' => $logType,
            'status_code' => $response->status(),
            'creation_date' => now(),
            'updated_date' => null,
        ];

        $responseData = null;

        if ($response instanceof JsonResponse) {
            $responseData = $response->getData(true);
        } elseif (
            ($contentType = $response->headers->get('Content-Type', ''))
            && (str_contains($contentType, 'application/json')
                || str_contains($contentType, 'application/problem+json'))
        ) {
            $responseData = json_decode($response->getContent(), true);
        }

        if (is_array($responseData)) {
            $excludeKeys = $request->attributes->get('log.request:response_excludes', []);
            $this->snapshot['response'] = $this->forgetResponsePaths($responseData, $excludeKeys);
        } elseif (! is_null($responseData)) {
            $this->snapshot['response'] = $responseData;
        }

        try {
            $this->requestLogRepository->create($this->snapshot);
        } catch (\Throwable $e) {
            Log::warning('No se pudo persistir el request log en el backend configurado', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function forgetResponsePaths(array $response, array $paths): array
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $segments = explode('.', $path);
            $this->forgetSegments($response, $segments);
        }

        return $response;
    }

    protected function forgetSegments(array &$response, array $segments): void
    {
        $key = array_shift($segments);

        if (! array_key_exists($key, $response)) {
            return;
        }

        if ($segments === []) {
            unset($response[$key]);
            return;
        }

        if (! is_array($response[$key])) {
            return;
        }

        $this->forgetSegments($response[$key], $segments);
    }
}
