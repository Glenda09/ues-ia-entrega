<?php

namespace App\Support;

class AuditStorageResolver
{
    public function relationalConnection(): string
    {
        return (string) config('audit.relational_connection', config('database.default', 'pgsql'));
    }
}
