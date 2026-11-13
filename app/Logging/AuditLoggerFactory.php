<?php

namespace App\Logging;

use App\Logging\Handlers\DatabaseSystemLogHandler;
use App\Services\Audit\SystemLogRepository;
use Monolog\Handler\FallbackGroupHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger;
use Monolog\LogRecord;

class AuditLoggerFactory
{
    /**
     * Crea un logger Monolog que escribe en PostgreSQL con respaldo a archivo.
     *
     * @param  array  $config  Configuración del canal.
     */
    public function __invoke(array $config): Logger
    {
        $tag = $config['tag'] ?? 'app';

        $logger = new Logger($tag);
        $path = storage_path('logs/laravel.log');
        $level = Logger::toMonologLevel(env('LOG_LEVEL', 'debug'));
        $fileHandler = new StreamHandler($path, $level);

        try {
            $databaseHandler = new DatabaseSystemLogHandler(app(SystemLogRepository::class), $level);
            $this->pushFallbackHandlers($logger, [$databaseHandler, $fileHandler]);
            $this->pushTagProcessor($logger, $tag);
        } catch (\Throwable $e) {
            $logger->pushHandler($fileHandler);
            $this->pushTagProcessor($logger, $tag, [
                'logger_fallback' => 'file',
                'logger_error' => $e->getMessage(),
            ]);
        }

        return $logger;
    }

    private function pushFallbackHandlers(Logger $logger, array $handlers): void
    {
        if (class_exists(FallbackGroupHandler::class)) {
            $logger->pushHandler(new FallbackGroupHandler($handlers));

            return;
        }

        $logger->pushHandler(new WhatFailureGroupHandler($handlers));
    }

    private function pushTagProcessor(Logger $logger, string $tag, array $additional = []): void
    {
        $logger->pushProcessor(function ($record) use ($tag, $additional) {
            $extras = array_merge(['tag' => $tag], $additional);

            if ($record instanceof LogRecord) {
                $record->extra = array_merge($record->extra, $extras);
                return $record;
            }

            if (is_array($record)) {
                $record['extra'] = array_merge($record['extra'] ?? [], $extras);
                return $record;
            }

            return $record;
        });
    }
}
