<?php

return [
    'relational_connection' => env('AUDIT_RELATIONAL_CONNECTION', env('DB_CONNECTION', 'pgsql')),
];
