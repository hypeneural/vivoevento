<?php

/**
 * WhatsApp Module Configuration
 *
 * Blocker de infra:
 * - APP_KEY MUST be configured and stable before deploying this module
 * - Encrypted casts depend on APP_KEY for provider_token, provider_client_token, webhook_secret
 *
 * Checklist antes de produção:
 * [ ] APP_KEY configurado permanentemente
 * [ ] APP_KEY validado em staging
 * [ ] Teste manual encrypt/decrypt funcionando
 * [ ] Política de rotação de chave definida
 * [ ] Backups consideram APP_KEY
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    */

    'default_provider' => env('WHATSAPP_DEFAULT_PROVIDER', 'zapi'),

    /*
    |--------------------------------------------------------------------------
    | Provider Settings
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'zapi' => [
            'base_url' => env('ZAPI_BASE_URL', 'https://api.z-api.io'),
            'timeout'  => (int) env('ZAPI_TIMEOUT', 30),
            'retries'  => (int) env('ZAPI_RETRIES', 3),
        ],
        'evolution' => [
            'base_url' => env('EVOLUTION_BASE_URL', ''),
            'api_key'  => env('EVOLUTION_API_KEY', ''),
            'timeout'  => (int) env('EVOLUTION_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Filas dedicadas — isolamento operacional:
    | - whatsapp-inbound: prioridade ALTA (volume, latência)
    | - whatsapp-send:    prioridade MÉDIA-ALTA (retry, rate limit)
    | - whatsapp-sync:    prioridade BAIXA (polling, status)
    |
    */

    'queues' => [
        'inbound' => env('WHATSAPP_QUEUE_INBOUND', 'whatsapp-inbound'),
        'send'    => env('WHATSAPP_QUEUE_SEND', 'whatsapp-send'),
        'sync'    => env('WHATSAPP_QUEUE_SYNC', 'whatsapp-sync'),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code Polling
    |--------------------------------------------------------------------------
    */

    'qr_code' => [
        'poll_interval_seconds' => (int) env('WHATSAPP_QR_POLL_INTERVAL', 15),
        'max_attempts'          => (int) env('WHATSAPP_QR_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dispatch Logging
    |--------------------------------------------------------------------------
    */

    'dispatch_log' => [
        'mask_tokens'    => true,
        'retention_days' => (int) env('WHATSAPP_LOG_RETENTION_DAYS', 90),
    ],

];
