<?php

namespace App\Services\Audit;

use Carbon\CarbonInterface;

class RequestLogRepository
{
    public function __construct(private AuditModelResolver $resolver) {}

    public function create(array $data): void
    {
        $modelClass = $this->resolver->requestLogModelClass();
        $modelClass::query()->create($data);
    }

    public function query(): mixed
    {
        $modelClass = $this->resolver->requestLogModelClass();

        return $modelClass::query();
    }

    public function deleteOlderThan(CarbonInterface $threshold): int
    {
        $modelClass = $this->resolver->requestLogModelClass();

        return (int) $modelClass::query()
            ->where('creation_date', '<', $threshold)
            ->delete();
    }

    public function delete(string $id): bool
    {
        $modelClass = $this->resolver->requestLogModelClass();

        return (bool) $modelClass::query()->where('uuid', $id)->delete();
    }

    public function truncate(): void
    {
        $modelClass = $this->resolver->requestLogModelClass();
        $modelClass::query()->truncate();
    }
}
