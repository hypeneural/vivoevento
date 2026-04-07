<?php

use App\Modules\Billing\Enums\EventPackageAudience;
use App\Modules\Billing\Enums\EventPackageBillingMode;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\BillingOrderNotification;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPackage;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('billing.gateways.default', 'manual');
    config()->set('billing.gateways.subscription', 'manual');
    config()->set('billing.gateways.event_package', 'manual');
    config()->set('billing.payment_notifications.enabled', true);
    config()->set('billing.payment_notifications.allow_single_connected_fallback', true);
    config()->set('billing.payment_notifications.pix_button.enabled', true);
    config()->set('billing.payment_notifications.pix_button.type', 'EVP');
    config()->set('billing.payment_notifications.pix_button.merchant_name', 'Evento Vivo');
});

it('creates a lightweight direct-customer checkout with pending billing order', function () {
    $this->seedPermissions();

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'amount_cents' => 19900,
        'features' => [
            'hub.enabled' => 'true',
            'wall.enabled' => 'true',
            'play.enabled' => 'false',
            'media.retention_days' => '90',
            'media.max_photos' => '400',
            'gallery.watermark' => 'false',
        ],
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Mariana Alves',
        'whatsapp' => '(48) 99988-1111',
        'email' => 'mariana@example.com',
        'organization_name' => 'Mariana & Rafael',
        'device_name' => 'public-checkout-web',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Mariana & Rafael',
            'event_type' => 'wedding',
            'event_date' => '2026-11-15',
            'city' => 'Balneario Camboriu',
            'description' => 'Fluxo de compra direta para evento unico.',
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $orderId = $response->json('data.checkout.id');
    $eventId = $response->json('data.event.id');
    $userId = $response->json('data.user.id');
    $organizationId = $response->json('data.organization.id');

    expect($response->json('data.token'))->toBeString()->not->toBe('');
    expect($response->json('data.organization.type'))->toBe('direct_customer');
    expect($response->json('data.checkout.mode'))->toBe('event_package');
    expect($response->json('data.checkout.status'))->toBe('pending_payment');
    expect($response->json('data.checkout.package.code'))->toBe($package->code);
    expect($response->json('data.commercial_status.commercial_mode'))->toBe('none');
    expect($response->json('data.onboarding.next_path'))->toBe("/events/{$eventId}");

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'organization_id' => $organizationId,
        'event_id' => $eventId,
        'buyer_user_id' => $userId,
        'mode' => 'event_package',
        'status' => 'pending_payment',
        'total_cents' => 19900,
        'currency' => 'BRL',
    ]);

    $this->assertDatabaseHas('billing_order_items', [
        'billing_order_id' => $orderId,
        'item_type' => 'event_package',
        'reference_id' => $package->id,
        'unit_amount_cents' => 19900,
        'total_amount_cents' => 19900,
    ]);

    $this->assertDatabaseMissing('event_purchases', [
        'billing_order_id' => $orderId,
    ]);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $userId,
        'tokenable_type' => User::class,
        'name' => 'public-checkout-web',
    ]);

    $event = Event::query()->findOrFail($eventId);

    expect($event->commercial_mode?->value)->toBe('none');
    expect($event->retention_days)->toBe(90);
    expect($event->current_entitlements_json['modules']['wall'] ?? null)->toBeTrue();
});

it('stores structured payer and payment metadata for a pix checkout', function () {
    $this->seedPermissions();

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'amount_cents' => 19900,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Mariana Alves',
        'whatsapp' => '(48) 99988-1111',
        'email' => 'mariana@example.com',
        'organization_name' => 'Mariana & Rafael',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Mariana & Rafael',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Mariana Alves',
            'email' => 'mariana@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99988-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'complement' => 'Sala 2',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'pix',
            'pix' => [
                'expires_in' => 1800,
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $orderId = $response->json('data.checkout.id');

    expect($response->json('data.checkout.payment.method'))->toBe('pix');
    expect($response->json('data.checkout.payment.gateway_charge_id'))->toBeNull();
    expect($response->json('data.checkout.payment.expires_at'))->toBeNull();
    expect($response->json('data.checkout.payment.pix.qr_code'))->toBeNull();
    expect($response->json('data.checkout.payment.pix.qr_code_url'))->toBeNull();
    expect($response->json('data.checkout.payment.pix.expires_at'))->toBeNull();

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'payment_method' => 'pix',
        'gateway_status' => 'pending_payment',
    ]);

    $order = BillingOrder::query()->findOrFail($orderId);

    expect($order->customer_snapshot_json['document'] ?? null)->toBe('12345678909');
    expect($order->customer_snapshot_json['address']['zip_code'] ?? null)->toBe('88000000');
});

it('creates a real pagarme pix order and returns the local qr code payload', function () {
    $this->seedPermissions();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_test_123',
            'code' => 'gateway-order-123',
            'status' => 'pending',
            'charges' => [
                [
                    'id' => 'ch_test_123',
                    'status' => 'pending',
                    'payment_method' => 'pix',
                    'last_transaction' => [
                        'id' => 'pix_tx_123',
                        'qr_code' => '00020101021226890014br.gov.bcb.pix2567pix.example/qr/123',
                        'qr_code_url' => 'https://pagar.me/qr/ch_test_123.png',
                        'expires_at' => '2026-04-04T20:00:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'amount_cents' => 19900,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Mariana Alves',
        'whatsapp' => '(48) 99988-1111',
        'email' => 'mariana@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Mariana & Rafael',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Mariana Alves',
            'email' => 'mariana@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99988-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'complement' => 'Sala 2',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'pix',
            'pix' => [
                'expires_in' => 1800,
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.checkout.payment.provider'))->toBe('pagarme');
    expect($response->json('data.checkout.payment.method'))->toBe('pix');
    expect($response->json('data.checkout.payment.gateway_order_id'))->toBe('or_test_123');
    expect($response->json('data.checkout.payment.gateway_charge_id'))->toBe('ch_test_123');
    expect($response->json('data.checkout.payment.gateway_transaction_id'))->toBe('pix_tx_123');
    expect($response->json('data.checkout.payment.expires_at'))->toBe('2026-04-04T20:00:00.000000Z');
    expect($response->json('data.checkout.payment.pix.qr_code'))->toContain('000201010212');
    expect($response->json('data.checkout.payment.pix.qr_code_url'))->toBe('https://pagar.me/qr/ch_test_123.png');

    $orderId = $response->json('data.checkout.id');
    $orderUuid = $response->json('data.checkout.uuid');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'uuid' => $orderUuid,
        'payment_method' => 'pix',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_test_123',
        'gateway_charge_id' => 'ch_test_123',
        'gateway_transaction_id' => 'pix_tx_123',
        'gateway_status' => 'pending_payment',
        'idempotency_key' => "billing-order:{$orderUuid}:attempt:1",
    ]);

    Http::assertSent(function (HttpRequest $request) use ($orderUuid) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/orders'
            && ($request->header('Idempotency-Key')[0] ?? null) === "billing-order:{$orderUuid}:attempt:1"
            && data_get($request->data(), 'payments.0.payment_method') === 'pix'
            && data_get($request->data(), 'metadata.billing_order_uuid') === $orderUuid;
    });
});

it('queues a whatsapp notification when pagarme pix is generated locally', function () {
    Queue::fake();

    $this->seedPermissions();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'is_default' => true,
    ]);

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('billing.payment_notifications.whatsapp_instance_id', $instance->id);
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_test_notify_123',
            'code' => 'gateway-order-notify-123',
            'status' => 'pending',
            'charges' => [
                [
                    'id' => 'ch_test_notify_123',
                    'status' => 'pending',
                    'payment_method' => 'pix',
                    'last_transaction' => [
                        'id' => 'pix_tx_notify_123',
                        'qr_code' => '00020101021226890014br.gov.bcb.pix2567pix.example/qr/notify123',
                        'qr_code_url' => 'https://pagar.me/qr/ch_test_notify_123.png',
                        'expires_at' => '2026-04-05T20:00:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'amount_cents' => 24900,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Mariana Alves',
        'whatsapp' => '(48) 99988-1111',
        'email' => 'mariana@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Mariana & Rafael',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Mariana Alves',
            'email' => 'mariana@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99988-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'pix',
            'pix' => [
                'expires_in' => 1800,
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $orderId = $response->json('data.checkout.id');
    expect($response->json('data.checkout.payment.whatsapp.pix_generated.status'))->toBe('pending');
    expect($response->json('data.checkout.payment.whatsapp.pix_generated.recipient_phone'))->toBe('5548999881111');
    expect($response->json('data.checkout.payment.whatsapp.pix_generated.pix_button_enabled'))->toBeNull();
    expect($response->json('data.checkout.payment.whatsapp.pix_generated.pix_button_message_id'))->toBeNull();

    $this->assertDatabaseHas('billing_order_notifications', [
        'billing_order_id' => $orderId,
        'notification_type' => 'pix_generated',
        'channel' => 'whatsapp',
        'status' => 'queued',
        'recipient_phone' => '5548999881111',
        'whatsapp_instance_id' => $instance->id,
    ]);

    $notification = BillingOrderNotification::query()
        ->where('billing_order_id', $orderId)
        ->where('notification_type', 'pix_generated')
        ->firstOrFail();

    expect($notification->whatsapp_message_id)->not()->toBeNull();
    expect(data_get($notification->context_json, 'delivery.pix_button_message_id'))->not()->toBeNull();

    $this->assertDatabaseHas('whatsapp_messages', [
        'id' => $notification->whatsapp_message_id,
        'instance_id' => $instance->id,
        'recipient_phone' => '5548999881111',
    ]);

    $this->assertDatabaseHas('whatsapp_messages', [
        'id' => data_get($notification->context_json, 'delivery.pix_button_message_id'),
        'instance_id' => $instance->id,
        'recipient_phone' => '5548999881111',
        'type' => 'pix',
    ]);

    Queue::assertPushedTimes(SendWhatsAppMessageJob::class, 2);
});

it('keeps only the text notification when the whatsapp provider does not support pix button', function () {
    Queue::fake();

    $this->seedPermissions();

    $instance = WhatsAppInstance::factory()->evolution()->connected()->create([
        'is_default' => true,
    ]);

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('billing.payment_notifications.whatsapp_instance_id', $instance->id);
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_test_notify_evolution_123',
            'code' => 'gateway-order-notify-evolution-123',
            'status' => 'pending',
            'charges' => [
                [
                    'id' => 'ch_test_notify_evolution_123',
                    'status' => 'pending',
                    'payment_method' => 'pix',
                    'last_transaction' => [
                        'id' => 'pix_tx_notify_evolution_123',
                        'qr_code' => '00020101021226890014br.gov.bcb.pix2567pix.example/qr/notify-evolution-123',
                        'qr_code_url' => 'https://pagar.me/qr/ch_test_notify_evolution_123.png',
                        'expires_at' => '2026-04-05T20:00:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'amount_cents' => 24900,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Mariana Alves',
        'whatsapp' => '(48) 99988-1111',
        'email' => 'mariana@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Mariana & Rafael',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Mariana Alves',
            'email' => 'mariana@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99988-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'pix',
            'pix' => [
                'expires_in' => 1800,
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $notification = BillingOrderNotification::query()
        ->where('billing_order_id', $response->json('data.checkout.id'))
        ->where('notification_type', 'pix_generated')
        ->firstOrFail();

    expect($response->json('data.checkout.payment.whatsapp.pix_generated.status'))->toBe('pending');
    expect($response->json('data.checkout.payment.whatsapp.pix_generated.pix_button_enabled'))->toBeNull();
    expect($response->json('data.checkout.payment.whatsapp.pix_generated.pix_button_message_id'))->toBeNull();
    expect(data_get($notification->context_json, 'delivery.pix_button_enabled'))->toBeFalse();
    expect(data_get($notification->context_json, 'delivery.pix_button_message_id'))->toBeNull();

    $this->assertDatabaseCount('whatsapp_messages', 1);
    Queue::assertPushedTimes(SendWhatsAppMessageJob::class, 1);
});

it('accepts a credit-card checkout contract and persists the selected payment method', function () {
    $this->seedPermissions();

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Camila Rocha',
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Camila & Bruno',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Camila Rocha',
            'email' => 'camila@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99977-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'credit_card',
            'credit_card' => [
                'installments' => 1,
                'statement_descriptor' => 'EVENTOVIVO',
                'card_token' => 'tok_test_123',
                'billing_address' => [
                    'street' => 'Rua Exemplo',
                    'number' => '123',
                    'district' => 'Centro',
                    'zip_code' => '88000000',
                    'city' => 'Florianopolis',
                    'state' => 'SC',
                    'country' => 'BR',
                ],
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $orderId = $response->json('data.checkout.id');

    expect($response->json('data.checkout.payment.method'))->toBe('credit_card');
    expect($response->json('data.checkout.payment.credit_card.installments'))->toBe(1);

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'payment_method' => 'credit_card',
    ]);

    $order = BillingOrder::query()->findOrFail($orderId);

    expect($order->customer_snapshot_json['document'] ?? null)->toBe('12345678909');
    expect(data_get($order->metadata_json, 'payment.credit_card.card_token'))->toBeNull();
    expect(data_get($order->metadata_json, 'payment.credit_card.has_card_token'))->toBeTrue();
});

it('creates a real pagarme credit-card order and activates the purchase when the gateway responds paid', function () {
    $this->seedPermissions();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/customers' => Http::response([
            'id' => 'cus_card_paid_123',
            'email' => 'camila@example.com',
        ], 200),
        'https://api.pagar.me/core/v5/customers/cus_card_paid_123/cards' => Http::response([
            'id' => 'card_paid_123',
        ], 200),
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_card_paid_123',
            'code' => 'gateway-card-paid-123',
            'status' => 'paid',
            'charges' => [
                [
                    'id' => 'ch_card_paid_123',
                    'status' => 'paid',
                    'payment_method' => 'credit_card',
                    'last_transaction' => [
                        'id' => 'tx_card_paid_123',
                        'status' => 'paid',
                        'acquirer_message' => 'Transacao aprovada',
                        'acquirer_return_code' => '00',
                        'card' => [
                            'id' => 'card_paid_123',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
        'amount_cents' => 19900,
        'features' => [
            'wall.enabled' => 'true',
            'play.enabled' => 'true',
            'media.retention_days' => '90',
        ],
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Camila Rocha',
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Camila & Bruno',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Camila Rocha',
            'email' => 'camila@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99977-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'credit_card',
            'credit_card' => [
                'installments' => 1,
                'statement_descriptor' => 'EVENTOVIVO',
                'card_token' => 'tok_test_paid_123',
                'billing_address' => [
                    'street' => 'Rua Exemplo',
                    'number' => '123',
                    'district' => 'Centro',
                    'zip_code' => '88000000',
                    'city' => 'Florianopolis',
                    'state' => 'SC',
                    'country' => 'BR',
                ],
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $orderId = $response->json('data.checkout.id');
    $eventId = $response->json('data.event.id');

    expect($response->json('data.checkout.status'))->toBe('paid');
    expect($response->json('data.purchase.status'))->toBe('paid');
    expect($response->json('data.checkout.payment.provider'))->toBe('pagarme');
    expect($response->json('data.checkout.payment.method'))->toBe('credit_card');
    expect($response->json('data.checkout.payment.gateway_order_id'))->toBe('or_card_paid_123');
    expect($response->json('data.checkout.payment.gateway_charge_id'))->toBe('ch_card_paid_123');
    expect($response->json('data.checkout.payment.gateway_transaction_id'))->toBe('tx_card_paid_123');
    expect($response->json('data.checkout.payment.credit_card.acquirer_message'))->toBe('Transacao aprovada');
    expect($response->json('data.checkout.payment.credit_card.acquirer_return_code'))->toBe('00');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'status' => 'paid',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_card_paid_123',
        'gateway_charge_id' => 'ch_card_paid_123',
        'gateway_transaction_id' => 'tx_card_paid_123',
        'gateway_status' => 'paid',
    ]);

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $orderId,
        'status' => 'paid',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_card_paid_123',
        'gateway_charge_id' => 'ch_card_paid_123',
        'gateway_transaction_id' => 'tx_card_paid_123',
        'gateway_status' => 'paid',
        'acquirer_message' => 'Transacao aprovada',
        'acquirer_return_code' => '00',
    ]);

    $this->assertDatabaseHas('event_purchases', [
        'billing_order_id' => $orderId,
        'event_id' => $eventId,
        'package_id' => $package->id,
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'event_id' => $eventId,
        'package_id' => $package->id,
        'source_type' => 'event_purchase',
        'status' => 'active',
    ]);

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers'
            && data_get($request->data(), 'email') === 'camila@example.com'
            && data_get($request->data(), 'phones.mobile_phone.number') === '999771111';
    });

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers/cus_card_paid_123/cards'
            && data_get($request->data(), 'token') === 'tok_test_paid_123';
    });

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/orders'
            && data_get($request->data(), 'customer_id') === 'cus_card_paid_123'
            && data_get($request->data(), 'payments.0.credit_card.card_id') === 'card_paid_123'
            && data_get($request->data(), 'payments.0.credit_card.card_token') === null
            && data_get($request->data(), 'customer') === null;
    });
});

it('creates a real pagarme credit-card order and marks the checkout as failed when the gateway responds failed', function () {
    $this->seedPermissions();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/customers' => Http::response([
            'id' => 'cus_card_failed_123',
            'email' => 'camila@example.com',
        ], 200),
        'https://api.pagar.me/core/v5/customers/cus_card_failed_123/cards' => Http::response([
            'id' => 'card_failed_123',
        ], 200),
        'https://api.pagar.me/core/v5/orders' => Http::response([
            'id' => 'or_card_failed_123',
            'code' => 'gateway-card-failed-123',
            'status' => 'failed',
            'charges' => [
                [
                    'id' => 'ch_card_failed_123',
                    'status' => 'failed',
                    'payment_method' => 'credit_card',
                    'last_transaction' => [
                        'id' => 'tx_card_failed_123',
                        'status' => 'failed',
                        'acquirer_message' => 'Nao autorizado',
                        'acquirer_return_code' => '51',
                        'card' => [
                            'id' => 'card_failed_123',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Camila Rocha',
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Camila & Bruno',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Camila Rocha',
            'email' => 'camila@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99977-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'credit_card',
            'credit_card' => [
                'installments' => 1,
                'statement_descriptor' => 'EVENTOVIVO',
                'card_token' => 'tok_test_failed_123',
                'billing_address' => [
                    'street' => 'Rua Exemplo',
                    'number' => '123',
                    'district' => 'Centro',
                    'zip_code' => '88000000',
                    'city' => 'Florianopolis',
                    'state' => 'SC',
                    'country' => 'BR',
                ],
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $orderId = $response->json('data.checkout.id');

    expect($response->json('data.checkout.status'))->toBe('failed');
    expect($response->json('data.purchase'))->toBeNull();
    expect($response->json('data.checkout.payment.credit_card.acquirer_message'))->toBe('Nao autorizado');
    expect($response->json('data.checkout.payment.credit_card.acquirer_return_code'))->toBe('51');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'status' => 'failed',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_card_failed_123',
        'gateway_charge_id' => 'ch_card_failed_123',
        'gateway_transaction_id' => 'tx_card_failed_123',
        'gateway_status' => 'failed',
    ]);

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $orderId,
        'status' => 'failed',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => 'or_card_failed_123',
        'gateway_charge_id' => 'ch_card_failed_123',
        'gateway_transaction_id' => 'tx_card_failed_123',
        'gateway_status' => 'failed',
        'acquirer_message' => 'Nao autorizado',
        'acquirer_return_code' => '51',
    ]);

    $this->assertDatabaseMissing('event_purchases', [
        'billing_order_id' => $orderId,
    ]);

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers'
            && data_get($request->data(), 'email') === 'camila@example.com';
    });

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers/cus_card_failed_123/cards'
            && data_get($request->data(), 'token') === 'tok_test_failed_123';
    });

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/orders'
            && data_get($request->data(), 'customer_id') === 'cus_card_failed_123'
            && data_get($request->data(), 'payments.0.credit_card.card_id') === 'card_failed_123'
            && data_get($request->data(), 'payments.0.credit_card.card_token') === null
            && data_get($request->data(), 'customer') === null;
    });
});

it('marks the checkout as failed when pagarme rejects the credit card before order creation', function () {
    $this->seedPermissions();

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://api.pagar.me/core/v5/customers' => Http::response([
            'id' => 'cus_card_verification_failed_123',
            'email' => 'camila@example.com',
        ], 200),
        'https://api.pagar.me/core/v5/customers/cus_card_verification_failed_123/cards' => Http::response([
            'message' => 'Could not create credit card. The card verification failed.',
        ], 412),
    ]);

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Camila Rocha',
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Camila & Bruno',
            'event_type' => 'wedding',
        ],
        'payer' => [
            'name' => 'Camila Rocha',
            'email' => 'camila@example.com',
            'document' => '12345678909',
            'document_type' => 'CPF',
            'phone' => '(48) 99977-1111',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'district' => 'Centro',
                'zip_code' => '88000000',
                'city' => 'Florianopolis',
                'state' => 'SC',
                'country' => 'BR',
            ],
        ],
        'payment' => [
            'method' => 'credit_card',
            'credit_card' => [
                'installments' => 1,
                'statement_descriptor' => 'EVENTOVIVO',
                'card_token' => 'tok_test_verification_failed_123',
                'billing_address' => [
                    'street' => 'Rua Exemplo',
                    'number' => '123',
                    'district' => 'Centro',
                    'zip_code' => '88000000',
                    'city' => 'Florianopolis',
                    'state' => 'SC',
                    'country' => 'BR',
                ],
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.checkout.status'))->toBe('failed');
    expect($response->json('data.purchase'))->toBeNull();
    expect($response->json('data.checkout.payment.method'))->toBe('credit_card');
    expect($response->json('data.checkout.payment.gateway_order_id'))->toBeNull();
    expect($response->json('data.checkout.payment.credit_card.acquirer_message'))
        ->toBe('Could not create credit card. The card verification failed.');

    $orderId = $response->json('data.checkout.id');

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'status' => 'failed',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => null,
        'gateway_status' => 'failed',
    ]);

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $orderId,
        'status' => 'failed',
        'payment_method' => 'credit_card',
        'gateway_provider' => 'pagarme',
        'gateway_order_id' => null,
        'gateway_status' => 'failed',
        'acquirer_message' => 'Could not create credit card. The card verification failed.',
    ]);

    $this->assertDatabaseMissing('event_purchases', [
        'billing_order_id' => $orderId,
    ]);

    Http::assertSent(function (HttpRequest $request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.pagar.me/core/v5/customers/cus_card_verification_failed_123/cards'
            && data_get($request->data(), 'token') === 'tok_test_verification_failed_123';
    });

    Http::assertNotSent(function (HttpRequest $request) {
        return $request->url() === 'https://api.pagar.me/core/v5/orders';
    });
});

it('confirms a public event checkout and creates purchase and grant idempotently', function () {
    $this->seedPermissions();

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::Both->value,
        'amount_cents' => 29900,
        'features' => [
            'hub.enabled' => 'true',
            'wall.enabled' => 'true',
            'play.enabled' => 'true',
            'media.retention_days' => '180',
            'media.max_photos' => '800',
            'gallery.watermark' => 'false',
            'white_label.enabled' => 'true',
        ],
    ]);

    $createResponse = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Camila Rocha',
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Camila & Bruno',
            'event_type' => 'wedding',
            'event_date' => '2026-12-05',
            'city' => 'Florianopolis',
        ],
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $orderId = $createResponse->json('data.checkout.id');
    $orderUuid = $createResponse->json('data.checkout.uuid');
    $eventId = $createResponse->json('data.event.id');

    $confirmResponse = $this->apiPost("/public/event-checkouts/{$orderUuid}/confirm", [
        'gateway_provider' => 'manual_test',
        'gateway_order_id' => 'order_test_123',
    ]);

    $this->assertApiSuccess($confirmResponse);

    expect($confirmResponse->json('data.checkout.status'))->toBe('paid');
    expect($confirmResponse->json('data.purchase.status'))->toBe('paid');
    expect($confirmResponse->json('data.commercial_status.commercial_mode'))->toBe('single_purchase');

    $purchase = EventPurchase::query()->where('billing_order_id', $orderId)->firstOrFail();

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'status' => 'paid',
        'gateway_provider' => 'manual_test',
        'gateway_order_id' => 'order_test_123',
    ]);

    $this->assertDatabaseHas('event_purchases', [
        'id' => $purchase->id,
        'billing_order_id' => $orderId,
        'event_id' => $eventId,
        'package_id' => $package->id,
        'status' => 'paid',
        'price_snapshot_cents' => 29900,
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'event_id' => $eventId,
        'source_type' => 'event_purchase',
        'source_id' => $purchase->id,
        'package_id' => $package->id,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('payments', [
        'billing_order_id' => $orderId,
        'status' => 'paid',
        'gateway_provider' => 'manual_test',
        'gateway_payment_id' => 'order_test_123',
    ]);

    $this->assertDatabaseHas('invoices', [
        'billing_order_id' => $orderId,
        'status' => 'paid',
        'amount_cents' => 29900,
        'currency' => 'BRL',
    ]);

    $repeatConfirmResponse = $this->apiPost("/public/event-checkouts/{$orderUuid}/confirm", []);

    $this->assertApiSuccess($repeatConfirmResponse);
    expect($repeatConfirmResponse->json('data.checkout.status'))->toBe('paid');
    expect(EventPurchase::query()->where('billing_order_id', $orderId)->count())->toBe(1);
    expect(EventAccessGrant::query()->where('event_id', $eventId)->where('source_type', 'event_purchase')->count())->toBe(1);

    $event = Event::query()->findOrFail($eventId);

    expect($event->commercial_mode?->value)->toBe('single_purchase');
    expect($event->current_entitlements_json['modules']['play'] ?? null)->toBeTrue();
    expect($event->current_entitlements_json['limits']['max_photos'] ?? null)->toBe(800);
    expect($event->current_entitlements_json['branding']['white_label'] ?? null)->toBeTrue();
});

it('requires full card checkout fields when payment method is credit_card', function () {
    $this->seedPermissions();

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Camila Rocha',
        'whatsapp' => '(48) 99977-1111',
        'email' => 'camila@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Camila & Bruno',
            'event_type' => 'wedding',
        ],
        'payment' => [
            'method' => 'credit_card',
            'credit_card' => [
                'installments' => 1,
            ],
        ],
    ]);

    $this->assertApiValidationError($response, [
        'payer',
        'payment.credit_card.card_token',
    ]);
});

it('returns the local status of a public checkout by uuid', function () {
    $this->seedPermissions();

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
    ]);

    $createResponse = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Mariana Alves',
        'whatsapp' => '(48) 99988-1111',
        'email' => 'mariana@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Mariana & Rafael',
            'event_type' => 'wedding',
        ],
        'payment' => [
            'method' => 'pix',
            'pix' => [
                'expires_in' => 1800,
            ],
        ],
    ]);

    $this->assertApiSuccess($createResponse, 201);

    $checkoutUuid = $createResponse->json('data.checkout.uuid');

    $statusResponse = $this->apiGet("/public/event-checkouts/{$checkoutUuid}");

    $this->assertApiSuccess($statusResponse);

    expect($statusResponse->json('data.checkout.uuid'))->toBe($checkoutUuid);
    expect($statusResponse->json('data.checkout.payment.method'))->toBe('pix');
    expect($statusResponse->json('data.checkout.status'))->toBe('pending_payment');
});

it('returns the public checkout status from local state without calling pagarme', function () {
    $this->seedPermissions();

    config()->set('billing.gateways.event_package', 'manual');

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::DirectCustomer->value,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Mariana Alves',
        'whatsapp' => '(48) 99988-1111',
        'email' => 'mariana@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Casamento Mariana & Rafael',
            'event_type' => 'wedding',
        ],
        'payment' => [
            'method' => 'pix',
            'pix' => [
                'expires_in' => 1800,
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $checkoutUuid = $response->json('data.checkout.uuid');

    config()->set('billing.gateways.event_package', 'pagarme');
    config()->set('services.pagarme', [
        'base_url' => 'https://api.pagar.me/core/v5/',
        'secret_key' => 'sk_test_7611662845434f72bdb0986b69d54ce1',
        'public_key' => 'pk_test_jGWvy7PhpBukl396',
        'statement_descriptor' => 'EVENTOVIVO',
        'pix_expires_in' => 1800,
        'timeout' => 15,
        'connect_timeout' => 5,
        'retry_times' => 1,
        'retry_sleep_ms' => 0,
    ]);

    Http::preventStrayRequests();

    $statusResponse = $this->apiGet("/public/event-checkouts/{$checkoutUuid}");

    $this->assertApiSuccess($statusResponse);
    $statusResponse->assertJsonPath('data.checkout.uuid', $checkoutUuid);
    $statusResponse->assertJsonPath('data.checkout.status', 'pending_payment');
});

it('rejects public checkout when package is not available to direct customers', function () {
    $this->seedPermissions();

    $package = createPublicEventPackage([
        'target_audience' => EventPackageAudience::Partner->value,
    ]);

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Ana Souza',
        'whatsapp' => '(48) 99966-1111',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Evento Ana',
            'event_type' => 'birthday',
        ],
    ]);

    $this->assertApiValidationError($response, ['package_id']);
});

it('rejects public checkout creation when whatsapp already belongs to an existing account', function () {
    $this->seedPermissions();

    User::factory()->create([
        'phone' => '5548999661111',
    ]);

    $package = createPublicEventPackage();

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Ana Souza',
        'whatsapp' => '(48) 99966-1111',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Evento Ana',
            'event_type' => 'birthday',
        ],
    ]);

    $this->assertApiValidationError($response, ['whatsapp']);
});

it('allows an authenticated existing account to resume the public checkout without creating another user or organization', function () {
    $this->seedPermissions();

    $organization = \App\Modules\Organizations\Models\Organization::factory()->create([
        'name' => 'Organizacao Existente',
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'name' => 'Ana Souza',
        'phone' => '5548999661111',
        'email' => 'ana@example.com',
        'status' => 'active',
    ]);

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $user->assignRole('partner-owner');
    $this->actingAs($user);

    $package = createPublicEventPackage();

    $response = $this->apiPost('/public/event-checkouts', [
        'responsible_name' => 'Ana Souza',
        'whatsapp' => '(48) 99966-1111',
        'email' => 'ana@example.com',
        'package_id' => $package->id,
        'event' => [
            'title' => 'Evento Ana',
            'event_type' => 'birthday',
        ],
        'payment' => [
            'method' => 'pix',
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $orderId = $response->json('data.checkout.id');
    $eventId = $response->json('data.event.id');

    expect($response->json('data.user.id'))->toBe($user->id);
    expect($response->json('data.organization.id'))->toBe($organization->id);
    expect($response->json('data.token'))->toBeNull();
    expect(User::query()->count())->toBe(1);

    $this->assertDatabaseHas('billing_orders', [
        'id' => $orderId,
        'organization_id' => $organization->id,
        'buyer_user_id' => $user->id,
        'event_id' => $eventId,
        'status' => 'pending_payment',
    ]);

    $this->assertDatabaseCount('organization_members', 1);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
        'name' => 'public-event-checkout',
    ]);
});

function createPublicEventPackage(array $overrides = []): EventPackage
{
    $package = EventPackage::factory()->create([
        'target_audience' => $overrides['target_audience'] ?? EventPackageAudience::Both->value,
        'is_active' => $overrides['is_active'] ?? true,
    ]);

    $package->prices()->create([
        'billing_mode' => EventPackageBillingMode::OneTime->value,
        'currency' => 'BRL',
        'amount_cents' => $overrides['amount_cents'] ?? 19900,
        'is_active' => true,
        'is_default' => true,
    ]);

    foreach (($overrides['features'] ?? [
        'hub.enabled' => 'true',
        'wall.enabled' => 'true',
        'play.enabled' => 'false',
        'media.retention_days' => '60',
        'media.max_photos' => '300',
    ]) as $featureKey => $featureValue) {
        $package->features()->create([
            'feature_key' => $featureKey,
            'feature_value' => $featureValue,
        ]);
    }

    return $package->fresh(['prices', 'features']);
}
