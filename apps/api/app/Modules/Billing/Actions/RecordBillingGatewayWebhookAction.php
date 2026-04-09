<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingGatewayEvent;
use App\Modules\Billing\Models\BillingOrder;
use Illuminate\Support\Str;

class RecordBillingGatewayWebhookAction
{
    public function execute(array $normalizedWebhook): BillingGatewayEvent
    {
        $wasCreated = false;

        /** @var BillingGatewayEvent $event */
        $event = BillingGatewayEvent::query()->firstOrNew([
            'provider_key' => $normalizedWebhook['provider_key'],
            'event_key' => $normalizedWebhook['event_key'],
        ]);

        $event->fill([
            'event_type' => $normalizedWebhook['event_type'],
            'hook_id' => $normalizedWebhook['hook_id'] ?? null,
            'billing_order_id' => $this->resolveBillingOrderId($normalizedWebhook),
            'gateway_order_id' => $normalizedWebhook['gateway_order_id'] ?? null,
            'gateway_subscription_id' => $normalizedWebhook['gateway_subscription_id'] ?? null,
            'gateway_invoice_id' => $normalizedWebhook['gateway_invoice_id'] ?? null,
            'gateway_cycle_id' => $normalizedWebhook['gateway_cycle_id'] ?? null,
            'gateway_customer_id' => $normalizedWebhook['gateway_customer_id'] ?? null,
            'gateway_charge_id' => $normalizedWebhook['gateway_charge_id'] ?? null,
            'gateway_transaction_id' => $normalizedWebhook['gateway_transaction_id'] ?? null,
            'occurred_at' => $normalizedWebhook['occurred_at'] ?? null,
            'headers_json' => $normalizedWebhook['headers'] ?? [],
            'payload_hash' => $this->makePayloadHash($normalizedWebhook['payload'] ?? []),
            'payload_json' => $normalizedWebhook['payload'] ?? [],
        ]);

        if (! $event->exists) {
            $wasCreated = true;
            $event->status = 'pending';
        }

        $event->save();

        return tap($event->fresh(), function (BillingGatewayEvent $fresh) use ($wasCreated) {
            $fresh->setAttribute('record_was_created', $wasCreated);
        });
    }

    private function resolveBillingOrderId(array $normalizedWebhook): ?int
    {
        $billingOrderUuid = $normalizedWebhook['billing_order_uuid'] ?? null;

        if (is_string($billingOrderUuid) && Str::isUuid($billingOrderUuid)) {
            return BillingOrder::query()
                ->where('uuid', $billingOrderUuid)
                ->value('id');
        }

        if (! empty($normalizedWebhook['gateway_order_id'])) {
            return BillingOrder::query()
                ->where('gateway_order_id', $normalizedWebhook['gateway_order_id'])
                ->value('id');
        }

        return null;
    }

    private function makePayloadHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
