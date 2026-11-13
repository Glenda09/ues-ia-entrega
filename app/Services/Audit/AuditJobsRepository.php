<?php

namespace App\Services\Audit;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AuditJobsRepository
{
    public function __construct(private AuditModelResolver $resolver) {}

    public function paginate(array $filters, int $perPage, string $order = 'desc'): LengthAwarePaginator
    {
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $query = DB::connection($this->resolver->relationalConnection())->table('jobs');

        if (! empty($filters['queue'])) {
            $query->where('queue', $filters['queue']);
        }

        if (! empty($filters['created_at'])) {
            $fecha = Carbon::parse((string) $filters['created_at'])->format('Y-m-d');
            $inicio = Carbon::parse($fecha)->startOfDay()->getTimestamp();
            $fin = Carbon::parse($fecha)->endOfDay()->getTimestamp();
            $query->whereBetween('created_at', [$inicio, $fin]);
        }

        $paginator = $query
            ->orderBy('created_at', $order)
            ->paginate($perPage);

        $paginator->setCollection($paginator->getCollection()->map(function ($row) {
            return [
                'uuid' => (string) ($row->id ?? ''),
                'queue' => $row->queue,
                'created_at' => Carbon::createFromTimestampUTC((int) $row->created_at)->toIso8601String(),
            ];
        }));

        return $paginator;
    }

    public function truncate(): void
    {
        DB::connection($this->resolver->relationalConnection())->table('jobs')->truncate();
    }

    public function create(array $data): void
    {
        $timestamp = isset($data['created_at'])
            ? Carbon::parse((string) $data['created_at'])->getTimestamp()
            : now()->getTimestamp();

        DB::connection($this->resolver->relationalConnection())
            ->table('jobs')
            ->insert([
                'queue' => (string) ($data['queue'] ?? 'default'),
                'payload' => (string) ($data['payload'] ?? '{}'),
                'attempts' => (int) ($data['attempts'] ?? 0),
                'reserved_at' => null,
                'available_at' => (int) ($data['available_at'] ?? $timestamp),
                'created_at' => $timestamp,
            ]);
    }

    public function countPendingByQueue(string $queue): int
    {
        return DB::connection($this->resolver->relationalConnection())
            ->table('jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->count();
    }

    public function delete(string $id): bool
    {
        return (bool) DB::connection($this->resolver->relationalConnection())
            ->table('jobs')
            ->where('id', $id)
            ->delete();
    }
}
