<?php

return [
    'queues' => [
        'high' => env('EVENT_PEOPLE_QUEUE_HIGH', 'event-people-high'),
        'medium' => env('EVENT_PEOPLE_QUEUE_MEDIUM', 'event-people-medium'),
        'low' => env('EVENT_PEOPLE_QUEUE_LOW', 'event-people-low'),
    ],

    'aws_sync_rate_limit_per_minute' => (int) env('EVENT_PEOPLE_AWS_SYNC_RATE_LIMIT_PER_MINUTE', 30),
];
