<?php

return [
    'stats' => [
        'async_updates' => filter_var(env('PARTNER_STATS_ASYNC_UPDATES', false), FILTER_VALIDATE_BOOL),
        'queue' => env('PARTNER_STATS_QUEUE', 'analytics'),
        'unique_for' => (int) env('PARTNER_STATS_UNIQUE_FOR', 60),
    ],
];
