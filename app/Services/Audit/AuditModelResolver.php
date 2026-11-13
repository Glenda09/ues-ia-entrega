<?php

namespace App\Services\Audit;

use App\Models\Relational\Auth\AuthSesionUsuarioRelational;
use App\Models\Relational\RequestLogRelational;
use App\Models\Relational\SystemLogRelational;
use App\Support\AuditStorageResolver;

class AuditModelResolver
{
    public function __construct(private AuditStorageResolver $storageResolver) {}

    public function relationalConnection(): string
    {
        return $this->storageResolver->relationalConnection();
    }

    /**
     * @return class-string
     */
    public function sessionModelClass(): string
    {
        return AuthSesionUsuarioRelational::class;
    }

    /**
     * @return class-string
     */
    public function requestLogModelClass(): string
    {
        return RequestLogRelational::class;
    }

    /**
     * @return class-string
     */
    public function systemLogModelClass(): string
    {
        return SystemLogRelational::class;
    }
}
