<?php

namespace App\Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = (array) ($this->snapshot_json ?? []);
        $order = $this->whenLoaded('order');
        $payment = $this->whenLoaded('order', fn () => $this->order?->payments?->first());

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status?->value,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'issued_at' => $this->issued_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'order' => $order ? [
                'id' => $this->order->id,
                'uuid' => $this->order->uuid,
                'mode' => $this->order->mode?->value,
                'status' => $this->order->status?->value,
            ] : ($snapshot['order'] ?? null),
            'event' => $this->order?->event ? [
                'id' => $this->order->event->id,
                'title' => $this->order->event->title,
            ] : ($snapshot['event'] ?? null),
            'package' => $snapshot['package'] ?? null,
            'plan' => $snapshot['plan'] ?? null,
            'payment' => $payment ? [
                'id' => $payment->id,
                'status' => $payment->status?->value,
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'gateway_provider' => $payment->gateway_provider,
                'gateway_payment_id' => $payment->gateway_payment_id,
                'paid_at' => $payment->paid_at?->toISOString(),
            ] : ($snapshot['payment'] ?? null),
            'snapshot' => $snapshot,
        ];
    }
}
