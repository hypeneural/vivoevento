<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Jobs\ProcessBillingWebhookJob;
use App\Modules\Billing\Services\BillingGatewayManager;
use Illuminate\Support\Facades\DB;

class ReceiveBillingWebhookAction
{
    public function __construct(
        private readonly BillingGatewayManager $gatewayManager,
        private readonly RecordBillingGatewayWebhookAction $recordBillingGatewayWebhook,
    ) {}

    public function execute(string $provider, array $payload, array $headers = []): array
    {
        $gateway = $this->gatewayManager->forProvider($provider);
        $normalized = $gateway->parseWebhook($payload, $headers);
        $gatewayEvent = $this->recordBillingGatewayWebhook->execute($normalized);
        $wasCreated = (bool) ($gatewayEvent->getAttribute('record_was_created') ?? false);

        $duplicate = ! $wasCreated || in_array($gatewayEvent->status, ['processed', 'ignored'], true);
        $queued = $wasCreated && ! in_array($gatewayEvent->status, ['processed', 'ignored'], true);

        if ($queued) {
            $dispatch = fn () => ProcessBillingWebhookJob::dispatch($gatewayEvent->id);

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($dispatch);
            } else {
                $dispatch();
            }
        }

        return [
            'accepted' => true,
            'duplicate' => $duplicate,
            'queued' => $queued,
            'gateway_event' => [
                'id' => $gatewayEvent->id,
                'provider_key' => $gatewayEvent->provider_key,
                'event_key' => $gatewayEvent->event_key,
                'event_type' => $gatewayEvent->event_type,
                'status' => $gatewayEvent->status,
                'billing_order_id' => $gatewayEvent->billing_order_id,
                'gateway_order_id' => $gatewayEvent->gateway_order_id,
                'gateway_charge_id' => $gatewayEvent->gateway_charge_id,
                'gateway_transaction_id' => $gatewayEvent->gateway_transaction_id,
                'occurred_at' => $gatewayEvent->occurred_at?->toISOString(),
            ],
        ];
    }
}
