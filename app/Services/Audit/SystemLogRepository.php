<?php

namespace App\Services\Audit;

use Carbon\CarbonInterface;

class SystemLogRepository
{
    public function __construct(private AuditModelResolver $resolver) {}

    public function query(): mixed
    {
        $modelClass = $this->resolver->systemLogModelClass();

        return $modelClass::query();
    }

    public function create(array $data): void
    {
        $modelClass = $this->resolver->systemLogModelClass();
        $modelClass::query()->create($data);
    }

    public function deleteOlderThan(CarbonInterface $threshold): int
    {
        $modelClass = $this->resolver->systemLogModelClass();

        return (int) $modelClass::query()
            ->where('datetime', '<', $threshold)
            ->delete();
    }

    public function delete(string $id): bool
    {
        $modelClass = $this->resolver->systemLogModelClass();

        return (bool) $modelClass::query()->where('uuid', $id)->delete();
    }

    public function truncate(): void
    {
        $modelClass = $this->resolver->systemLogModelClass();
        $modelClass::query()->truncate();
    }
}
