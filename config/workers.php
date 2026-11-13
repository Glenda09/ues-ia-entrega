<?php

return [
    'defaults' => [
        'numprocs' => (int) env('QUEUE_WORKER_NUMPROCS', 1),
        'timeout' => (int) env('QUEUE_WORKER_TIMEOUT', 3600),
        'memory' => (int) env('QUEUE_WORKER_MEMORY', 2048),
        'tries' => (int) env('QUEUE_WORKER_TRIES', 1),
        'sleep' => (int) env('QUEUE_WORKER_SLEEP', 3),
        'startsecs' => (int) env('QUEUE_WORKER_STARTSECS', 5),
        'stopwaitsecs' => (int) env('QUEUE_WORKER_STOPWAITSECS', 600),
    ],

    'workers' => [
        'default' => [
            'queues' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('QUEUE_WORKER_QUEUES', 'default,request-logs'))
            ))),
        ],
    ],
];
