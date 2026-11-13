<?php

return [
    // Tiempo de vida del cache para catálogos en segundos. Default: 12 horas (43200s)
    'cache_ttl' => (int) env('CATALOG_CACHE_TTL', 60 * 60 * 12),
];
