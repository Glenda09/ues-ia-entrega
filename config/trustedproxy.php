<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza
    |--------------------------------------------------------------------------
    |
    | Lo lee el middleware global TrustProxies de Laravel. Detrás del balanceador
    | (nginx del host -> lb -> réplicas) indica qué saltos pueden declarar
    | X-Forwarded-For/Proto/Port para que request()->ip(), el esquema y los
    | límites de peticiones usen los datos del cliente real.
    |
    | Acepta IPs o rangos CIDR separados por coma, "*" (confiar en quien llama)
    | o null (no confiar en ninguno, comportamiento por defecto).
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
