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
        ->and($mapper->toInternalWebhookType('charge.paid', [
            'subscription' => ['id' => 'sub_recurring_123'],
            'invoice' => ['id' => 'inv_recurring_123'],
        ]))->toBe('charge.paid')
        ->and($mapper->toInternalWebhookType('order.payment_failed'))->toBe('payment.failed')
        ->and($mapper->toInternalWebhookType('charge.refunded'))->toBe('payment.refunded')
        ->and($mapper->toInternalWebhookType('charge.partial_canceled'))->toBe('payment.partially_refunded')
        ->and($mapper->toInternalWebhookType('charge.chargedback'))->toBe('payment.chargeback')
        ->and($mapper->toInternalWebhookType('charge.chargedback', [
            'subscription_id' => 'sub_recurring_123',
            'invoice_id' => 'inv_recurring_123',
        ]))->toBe('charge.chargedback')
        ->and($mapper->toInternalWebhookType('invoice.payment_failed'))->toBe('invoice.payment_failed')
        ->and($mapper->toInternalWebhookType('subscription.updated'))->toBe('subscription.updated')
        ->and($mapper->toInternalWebhookType('order.canceled'))->toBe('checkout.canceled');
});

it('projects recurring provider signals into separate contract billing and access statuses', function () {
    $mapper = app(PagarmeStatusMapper::class);

    expect($mapper->toRecurringContractStatus('active'))->toBe('active')
        ->and($mapper->toRecurringContractStatus('future'))->toBe('future')
        ->and($mapper->toRecurringContractStatus('canceled'))->toBe('canceled')
        ->and($mapper->toRecurringContractStatus('active', 'chargedback'))->toBe('canceled')
        ->and($mapper->toInvoiceStatus('pending'))->toBe('open')
        ->and($mapper->toInvoiceStatus('failed'))->toBe('failed')
        ->and($mapper->toInvoiceStatus('canceled'))->toBe('canceled')
        ->and($mapper->toPaymentStatus('chargedback'))->toBe('chargedback')
        ->and($mapper->toRecurringBillingStatus('pending', null))->toBe('pending')
        ->and($mapper->toRecurringBillingStatus('paid', null))->toBe('paid')
        ->and($mapper->toRecurringBillingStatus('failed', null))->toBe('grace_period')
        ->and($mapper->toRecurringBillingStatus('paid', 'chargedback'))->toBe('chargedback')
        ->and($mapper->toRecurringAccessStatus('active', 'paid'))->toBe('enabled')
        ->and($mapper->toRecurringAccessStatus('active', 'grace_period'))->toBe('grace_period')
        ->and($mapper->toRecurringAccessStatus('future', 'pending'))->toBe('provisioning')
        ->and($mapper->toRecurringAccessStatus('canceled', 'pending', true))->toBe('enabled')
        ->and($mapper->toRecurringAccessStatus('canceled', 'pending', false))->toBe('disabled')
        ->and($mapper->toRecurringAccessStatus('active', 'chargedback'))->toBe('disabled');
});
