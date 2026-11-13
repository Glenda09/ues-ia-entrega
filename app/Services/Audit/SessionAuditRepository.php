<?php

namespace App\Services\Audit;

class SessionAuditRepository
{
    public function __construct(private AuditModelResolver $resolver) {}

    public function create(array $data): void
    {
        $modelClass = $this->resolver->sessionModelClass();
        $modelClass::query()->create($data);
    }

    public function findByUserAndJti(string $userId, string $jti): mixed
    {
        $modelClass = $this->resolver->sessionModelClass();

        return $modelClass::query()
            ->where('id_usuario', $userId)
            ->where('jti', $jti)
            ->first();
    }

    public function menuByUserAndJti(string $userId, string $jti): array
    {
        $session = $this->findByUserAndJti($userId, $jti);

        if (! $session) {
            return [];
        }

        return is_array($session->menu) ? array_values($session->menu) : [];
    }

    public function truncate(): void
    {
        $modelClass = $this->resolver->sessionModelClass();
        $modelClass::query()->truncate();
    }
}
