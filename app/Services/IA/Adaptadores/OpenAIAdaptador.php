<?php

namespace App\Services\IA\Adaptadores;

use App\Support\IA\Citas;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Adaptador OpenAI (Responses API, store=false). Sin reintentos automáticos facturables.
 */
class OpenAIAdaptador implements AdaptadorIA
{
    public function __construct(private string $apiKey, private string $modelo, private int $timeout) {}

    public function proveedor(): string
    {
        return 'openai';
    }

    public function modelo(): string
    {
        return $this->modelo;
    }

    public function simulado(): bool
    {
        return false;
    }

    public function generar(array $mensajes, array $herramientas, int $maxSalida): array
    {
        $input = [];
        $instrucciones = null;
        foreach ($mensajes as $m) {
            if ($m['rol'] === 'system') {
                $instrucciones = trim(($instrucciones ?? '')."\n".$m['contenido']);
            } elseif ($m['rol'] === 'tool_call') {
                // Llamada emitida por el modelo en una inferencia anterior del mismo turno (formato nativo).
                $input[] = ['type' => 'function_call', 'call_id' => $m['id_llamada'], 'name' => $m['nombre'], 'arguments' => $m['contenido']];
            } elseif ($m['rol'] === 'tool') {
                // Resultado emparejado por call_id: sin esto el modelo no reconoce su llamada y la repite.
                $input[] = isset($m['id_llamada'])
                    ? ['type' => 'function_call_output', 'call_id' => $m['id_llamada'], 'output' => $m['contenido']]
                    : ['role' => 'user', 'content' => 'Resultado de herramienta '.($m['nombre'] ?? '').': '.$m['contenido']];
            } else {
                $input[] = ['role' => $m['rol'], 'content' => $m['contenido']];
            }
        }
        $tools = array_map(fn ($h) => ['type' => 'function', 'name' => $h['nombre'], 'description' => $h['descripcion'], 'parameters' => $h['parametros']], $herramientas);

        try {
            $resp = Http::withToken($this->apiKey)->timeout($this->timeout)->acceptJson()
                ->post('https://api.openai.com/v1/responses', array_filter([
                    'model' => $this->modelo,
                    'instructions' => $instrucciones,
                    'input' => $input,
                    'tools' => $tools ?: null,
                    'max_output_tokens' => $maxSalida,
                    'store' => false,
                ]));
        } catch (ConnectionException $e) {
            if (str_contains($e->getMessage(), 'resolve') || str_contains($e->getMessage(), 'refused')) {
                throw new FalloAntesDeEnvio($e->getMessage());
            }
            throw $e;
        }
        if ($resp->status() === 401 || $resp->status() === 400) {
            throw new FalloAntesDeEnvio('Petición rechazada por el proveedor: '.$resp->status());
        }
        $resp->throw();
        $json = $resp->json();
        $texto = '';
        $llamadas = [];
        foreach ($json['output'] ?? [] as $item) {
            if (($item['type'] ?? '') === 'function_call') {
                $llamadas[] = ['nombre' => $item['name'], 'argumentos' => json_decode($item['arguments'] ?? '{}', true) ?: [], 'id_llamada' => $item['call_id'] ?? null];
            }
            foreach ($item['content'] ?? [] as $c) {
                if (($c['type'] ?? '') === 'output_text') {
                    $texto .= $c['text'];
                }
            }
        }

        return [
            'texto' => $texto,
            'llamadas' => $llamadas,
            'citas' => Citas::extraer($texto),
            'uso' => ['entrada' => (int) ($json['usage']['input_tokens'] ?? 0), 'salida' => (int) ($json['usage']['output_tokens'] ?? 0)],
            'id_respuesta' => $json['id'] ?? null,
        ];
    }
}
