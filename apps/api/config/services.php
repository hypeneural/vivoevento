<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'face_search_aws' => [
        'access_key_id' => env('FACE_SEARCH_AWS_ACCESS_KEY_ID'),
        'secret_access_key' => env('FACE_SEARCH_AWS_SECRET_ACCESS_KEY'),
        'session_token' => env('FACE_SEARCH_AWS_SESSION_TOKEN'),
        'region' => env('FACE_SEARCH_AWS_REGION', env('AWS_REGION', env('AWS_DEFAULT_REGION', 'eu-central-1'))),
        'endpoint' => env('FACE_SEARCH_AWS_ENDPOINT'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'pagarme' => [
        'base_url' => env('PAGARME_BASE_URL', 'https://api.pagar.me/core/v5/'),
        'account_id' => env('PAGARME_ACCOUNT_ID'),
        'secret_key' => env('PAGARME_SECRET_KEY'),
        'public_key' => env('PAGARME_PUBLIC_KEY'),
        'webhook_basic_auth_user' => env('PAGARME_WEBHOOK_BASIC_AUTH_USER'),
        'webhook_basic_auth_password' => env('PAGARME_WEBHOOK_BASIC_AUTH_PASSWORD'),
        'statement_descriptor' => env('PAGARME_STATEMENT_DESCRIPTOR', 'EVENTOVIVO'),
        'pix_expires_in' => (int) env('PAGARME_PIX_EXPIRES_IN', 1800),
        'timeout' => (int) env('PAGARME_TIMEOUT', 15),
        'connect_timeout' => (int) env('PAGARME_CONNECT_TIMEOUT', 5),
        'retry_times' => (int) env('PAGARME_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('PAGARME_RETRY_SLEEP_MS', 100),
    ],

    'telegram' => [
        'base_url' => env('TELEGRAM_BASE_URL', 'https://api.telegram.org'),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret_token' => env('TELEGRAM_WEBHOOK_SECRET_TOKEN'),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 15),
        'connect_timeout' => (int) env('TELEGRAM_CONNECT_TIMEOUT', 5),
    ],

];
