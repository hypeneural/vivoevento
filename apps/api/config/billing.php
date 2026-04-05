<?php

use App\Modules\Billing\Services\ManualBillingGateway;
use App\Modules\Billing\Services\Pagarme\PagarmeBillingGateway;

return [
    'gateways' => [
        'default' => env('BILLING_GATEWAY_DEFAULT', 'manual'),
        'subscription' => env('BILLING_GATEWAY_SUBSCRIPTION', env('BILLING_GATEWAY_DEFAULT', 'manual')),
        'event_package' => env('BILLING_GATEWAY_EVENT_PACKAGE', env('BILLING_GATEWAY_DEFAULT', 'manual')),
        'providers' => [
            'manual' => ManualBillingGateway::class,
            'pagarme' => PagarmeBillingGateway::class,
        ],
    ],
    'access_delivery' => [
        'whatsapp_instance_id' => env('BILLING_ACCESS_WHATSAPP_INSTANCE_ID', env('WHATSAPP_AUTH_INSTANCE_ID')),
        'allow_single_connected_fallback' => (bool) env('BILLING_ACCESS_ALLOW_SINGLE_CONNECTED_FALLBACK', true),
    ],
    'payment_notifications' => [
        'enabled' => (bool) env('BILLING_PAYMENT_NOTIFICATIONS_ENABLED', true),
        'whatsapp_instance_id' => env(
            'BILLING_PAYMENT_NOTIFICATIONS_WHATSAPP_INSTANCE_ID',
            env('BILLING_ACCESS_WHATSAPP_INSTANCE_ID', env('WHATSAPP_AUTH_INSTANCE_ID'))
        ),
        'allow_single_connected_fallback' => (bool) env('BILLING_PAYMENT_NOTIFICATIONS_ALLOW_SINGLE_CONNECTED_FALLBACK', true),
    ],
];
