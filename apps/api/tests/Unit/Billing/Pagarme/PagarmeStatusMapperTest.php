<?php

use App\Modules\Billing\Services\Pagarme\PagarmeStatusMapper;

it('maps pagarme order and charge statuses into local billing statuses', function () {
    $mapper = app(PagarmeStatusMapper::class);

    expect($mapper->toBillingOrderStatus('pending'))->toBe('pending_payment')
        ->and($mapper->toBillingOrderStatus('paid'))->toBe('paid')
        ->and($mapper->toBillingOrderStatus('failed'))->toBe('failed')
        ->and($mapper->toBillingOrderStatus('canceled'))->toBe('canceled')
        ->and($mapper->toBillingOrderStatus('refunded'))->toBe('refunded')
        ->and($mapper->toBillingOrderStatus('partial_canceled'))->toBe('refunded')
        ->and($mapper->toBillingOrderStatus('chargedback'))->toBe('refunded')
        ->and($mapper->toPaymentStatus('waiting_payment'))->toBe('pending')
        ->and($mapper->toPaymentStatus('paid'))->toBe('paid')
        ->and($mapper->toPaymentStatus('failed'))->toBe('failed')
        ->and($mapper->toPaymentStatus('canceled'))->toBe('failed')
        ->and($mapper->toPaymentStatus('partial_canceled'))->toBe('refunded');
});

it('maps pagarme webhook types into the local billing event types', function () {
    $mapper = app(PagarmeStatusMapper::class);

    expect($mapper->toInternalWebhookType('order.paid'))->toBe('payment.paid')
        ->and($mapper->toInternalWebhookType('charge.paid'))->toBe('payment.paid')
        ->and($mapper->toInternalWebhookType('order.payment_failed'))->toBe('payment.failed')
        ->and($mapper->toInternalWebhookType('charge.refunded'))->toBe('payment.refunded')
        ->and($mapper->toInternalWebhookType('charge.partial_canceled'))->toBe('payment.partially_refunded')
        ->and($mapper->toInternalWebhookType('charge.chargedback'))->toBe('payment.chargeback')
        ->and($mapper->toInternalWebhookType('order.canceled'))->toBe('checkout.canceled');
});
