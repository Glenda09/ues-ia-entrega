<?php

namespace App\Tasks;

use App\Services\Audit\RequestLogRepository;
use App\Services\Audit\SystemLogRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PurgeLogsTask
{
    public function execute(): void
    {
        $retentionDays = (int) config('logging.logs_ttl', 30);

        if ($retentionDays <= 0) {
            Log::channel('system')->warning('Depuración de logs omitida: LOGS_TTL inválido', [
                'logs_ttl' => $retentionDays,
            ]);

            return;
        }

        $executedAt = Carbon::now();
        $threshold = $executedAt->copy()->subDays($retentionDays);

        $requestLogsDeleted = app(RequestLogRepository::class)->deleteOlderThan($threshold);
        $systemLogsDeleted = app(SystemLogRepository::class)->deleteOlderThan($threshold);

        Log::channel('system')->info(
            sprintf(
                'Depuración de logs completada: request_logs=%d, system_logs=%d',
                $requestLogsDeleted,
                $systemLogsDeleted
            ),
            [
                'fecha_ejecucion' => $executedAt->toIso8601String(),
                'logs_ttl' => $retentionDays,
                'request_logs_eliminados' => $requestLogsDeleted,
                'system_logs_eliminados' => $systemLogsDeleted,
            ]
        );
    }
}
