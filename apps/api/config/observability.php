<?php

return [
    'wall_log_channel' => env('LOG_WALL_REALTIME_CHANNEL', 'wall-realtime'),
    'queue_log_channel' => env('LOG_QUEUE_TELEMETRY_CHANNEL', 'queue-telemetry'),
    'operations_dashboard_roles' => array_values(array_filter(array_map(
        static fn (string $role): string => trim($role),
        explode(',', (string) env('OPERATIONS_DASHBOARD_ROLES', 'super-admin,platform-admin'))
    ))),
    'operations_dashboard_permission' => env('OPERATIONS_DASHBOARD_PERMISSION', 'audit.view'),
    'queue_busy_connection' => env('QUEUE_MONITOR_CONNECTION', 'redis'),
    'queue_busy_thresholds' => [
        'webhooks' => (int) env('QUEUE_BUSY_WEBHOOKS_MAX', 25),
        'media-variants' => (int) env('QUEUE_BUSY_MEDIA_VARIANTS_MAX', 50),
        'media-audit' => (int) env('QUEUE_BUSY_MEDIA_AUDIT_MAX', 50),
        'media-publish' => (int) env('QUEUE_BUSY_MEDIA_PUBLISH_MAX', 25),
        'broadcasts' => (int) env('QUEUE_BUSY_BROADCASTS_MAX', 50),
    ],
    'degradation' => [
        'media_safety_mode' => env('OPS_DEGRADE_MEDIA_SAFETY_MODE', 'normal'),
        'media_vlm_enabled' => filter_var(env('OPS_DEGRADE_MEDIA_VLM_ENABLED', true), FILTER_VALIDATE_BOOL),
        'face_index_enabled' => filter_var(env('OPS_DEGRADE_FACE_INDEX_ENABLED', true), FILTER_VALIDATE_BOOL),
    ],
];
