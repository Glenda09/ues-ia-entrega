<?php

/**
 * IA-BASE-01: configuración del proxy de IA (D10).
 *
 * Sin OPENAI_API_KEY (o con AI_PROVIDER=mock) se usa el adaptador simulado determinista.
 */
return [
    'proveedor' => env('AI_PROVIDER', 'openai'),
    'modelo' => env('AI_MODEL', 'gpt-6-luna'),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'presupuesto_usd' => env('AI_BUDGET_USD'),
    'max_tokens_entrada' => (int) env('AI_MAX_INPUT_TOKENS', 8000),
    'max_tokens_salida' => (int) env('AI_MAX_OUTPUT_TOKENS', 1000),
    'max_caracteres_mensaje' => (int) env('AI_MAX_MESSAGE_CHARS', 8000),
    'max_inferencias_turno' => (int) env('AI_MAX_INFERENCES_PER_TURN', 4),
    'max_herramientas_turno' => (int) env('AI_MAX_TOOLS_PER_TURN', 8),
    'timeout_segundos' => (int) env('AI_TIMEOUT_SECONDS', 60),
    'limite_turnos_propietario_minuto' => (int) env('AI_RATE_OWNER_PER_MINUTE', 10),
    'limite_turnos_ip_minuto' => (int) env('AI_RATE_IP_PER_MINUTE', 30),
    // Tarifa inicial del adaptador simulado (USD por millón de tokens). Separada de la contabilidad real.
    'tarifa_simulada' => ['entrada' => '0.10', 'salida' => '0.50'],
];
