<?php

return [
    'default_provider' => env('WHATSAPP_DEFAULT_PROVIDER', 'zapi'),

    'providers' => [
        'zapi' => [
            'base_url' => env('ZAPI_BASE_URL', 'https://api.z-api.io'),
        ],
    ],
];
