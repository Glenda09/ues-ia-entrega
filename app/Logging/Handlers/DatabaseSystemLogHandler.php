<?php

namespace App\Logging\Handlers;

use App\Services\Audit\SystemLogRepository;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class DatabaseSystemLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private SystemLogRepository $systemLogRepository,
        int|string|Level $level = 'debug',
        bool $bubble = true
    ) {
        if ($level instanceof Level) {
            $level = $level->value;
        }

        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->systemLogRepository->create([
            'uuid' => (string) Str::uuid(),
            'message' => $record->message,
            'context' => $record->context,
            'level' => $record->level->value,
            'level_name' => $record->level->getName(),
            'channel' => $record->channel,
            'datetime' => Carbon::instance($record->datetime),
            'extra' => $record->extra,
        ]);
    }
}
