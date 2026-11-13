<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        /*
         * Separación de lecturas y escrituras (despliegue con réplica, ver docs/deploy-vm-docker.md).
         * Solo se activa cuando DB_READ_HOST está definido; sin él la conexión queda idéntica.
         * DB_READ_HOST admite varios hosts separados por coma (Laravel elige uno al azar por petición).
         * Con sticky=true, tras una escritura la misma petición sigue leyendo del primario; las
         * transacciones y los bloqueos (lockForUpdate/sharedLock) siempre usan el primario.
         */
        'pgsql' => array_merge([
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ], env('DB_READ_HOST') ? [
            'read' => [
                'host' => array_values(array_filter(array_map('trim', explode(',', (string) env('DB_READ_HOST'))))),
            ],
            'write' => [
                'host' => [env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1'))],
            ],
            'sticky' => (bool) env('DB_STICKY', true),
        ] : []),

        /*
         * Siempre al primario y sin réplica. Se usa para estado operativo que se escribe en casi
         * cada petición (caché, rate limiter, locks, sesiones) mediante DB_CACHE_CONNECTION,
         * DB_CACHE_LOCK_CONNECTION y SESSION_CONNECTION. Al ser otra conexión, esas escrituras
         * no activan el modo sticky de "pgsql" y las lecturas de negocio siguen en la réplica.
         */
        'pgsql_primary' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

];
