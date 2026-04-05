<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Billing\Services\EventPackageSnapshotService;
use App\Modules\Events\Support\EventCommercialStatusService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivatePaidEventPackageOrderAction
{
    public function __construct(
        private readonly EventPackageSnapshotService $packageSnapshots,
        private readonly EventCommercialStatusService $commercialStatus,
        private readonly MarkBillingOrderAsPaidAction $markBillingOrderAsPaid,
    ) {}

    public function execute(BillingOrder $billingOrder, array $data = []): array
    {
        $confirmedAt = isset($data['confirmed_at']) ? Carbon::parse($data['confirmed_at']) : now();

        return DB::transaction(function () use ($billingOrder, $data, $confirmedAt) {
            /** @var BillingOrder $order */
            $order = BillingOrder::query()
                ->with([
                    'organization',
                    'buyer.roles',
                    'buyer.organizations',
                    'event.organization',
                    'event.modules',
                    'payments',
                    'invoices',
                    'items.eventPackage.prices',
                    'items.eventPackage.features',
                    'purchases',
                ])
                ->lockForUpdate()
                ->findOrFail($billingOrder->id);

            $item = $order->items->firstWhere('item_type', 'event_package') ?? $order->items->first();
            $package = $item?->eventPackage;

            if (! $item || ! $package) {
                throw ValidationException::withMessages([
                    'checkout' => ['Nao foi possivel localizar o pacote associado a este checkout.'],
                ]);
            }

            $event = $order->event;

            if (! $event) {
                throw ValidationException::withMessages([
                    'checkout' => ['Nao foi possivel localizar o evento associado ao checkout.'],
                ]);
            }

            if ($order->status?->isPaid()) {
                $purchase = $order->purchases
                    ->sortByDesc(fn ($candidate) => sprintf(
                        '%015d-%015d',
                        $candidate->purchased_at?->timestamp ?? 0,
                        $candidate->id,
                    ))
                    ->first();

                $event = $this->commercialStatus->sync($event->fresh(['organization', 'modules']));

                return [
                    'order' => $order->fresh(['items', 'event', 'payments', 'invoices']),
                    'payment' => $order->payments->sortByDesc('id')->first(),
                    'invoice' => $order->invoices->sortByDesc('id')->first(),
                    'purchase' => $purchase,
                    'event' => $event,
                    'package' => $package,
                ];
            }

            $snapshot = $this->packageSnapshots->build($package);

            $purchase = EventPurchase::query()->firstOrNew([
                'billing_order_id' => $order->id,
            ]);

            $purchase->fill([
                'organization_id' => $order->organization_id,
                'client_id' => $order->event?->client_id,
                'event_id' => $order->event_id,
                'plan_id' => null,
                'package_id' => $package->id,
                'price_snapshot_cents' => $order->total_cents,
                'currency' => $order->currency,
                'features_snapshot_json' => $snapshot['purchase_features_snapshot'],
                'status' => 'paid',
                'purchased_by_user_id' => $order->buyer_user_id,
                'purchased_at' => $confirmedAt,
            ]);
            $purchase->save();

            EventAccessGrant::query()->updateOrCreate(
                [
                    'event_id' => $order->event_id,
                    'source_type' => EventAccessGrantSourceType::EventPurchase->value,
                    'source_id' => $purchase->id,
                ],
                [
                    'organization_id' => $order->organization_id,
                    'package_id' => $package->id,
                    'status' => EventAccessGrantStatus::Active->value,
                    'priority' => EventAccessGrantSourceType::EventPurchase->defaultPriority(),
                    'merge_strategy' => EntitlementMergeStrategy::Replace->value,
                    'starts_at' => $confirmedAt,
                    'ends_at' => null,
                    'features_snapshot_json' => $snapshot['grant_features_snapshot'],
                    'limits_snapshot_json' => $snapshot['grant_limits_snapshot'],
                    'granted_by_user_id' => $order->buyer_user_id,
                    'notes' => 'Grant ativo gerado por checkout publico de evento.',
                    'metadata_json' => [
                        'journey' => 'public_event_checkout',
                        'billing_order_uuid' => $order->uuid,
                    ],
                ],
            );

            $event->forceFill([
                'purchased_plan_snapshot_json' => $snapshot['event_snapshot'],
            ])->save();

            $billingDocuments = $this->markBillingOrderAsPaid->execute($order, [
                'paid_at' => $confirmedAt,
                'gateway_provider' => $data['gateway_provider'] ?? $order->gateway_provider ?? 'manual',
                'gateway_order_id' => $data['gateway_order_id'] ?? $order->gateway_order_id,
                'gateway_payment_id' => $data['gateway_payment_id'] ?? $data['gateway_order_id'] ?? $order->gateway_order_id,
                'gateway_charge_id' => $data['gateway_charge_id'] ?? $order->gateway_charge_id,
                'gateway_transaction_id' => $data['gateway_transaction_id'] ?? $order->gateway_transaction_id,
                'gateway_status' => $data['gateway_status'] ?? 'paid',
                'payment_status' => $data['payment_status'] ?? 'paid',
                'payment_payload' => $data['payment_payload'] ?? [
                    'source' => 'public_event_checkout_confirm',
                    'billing_order_uuid' => $order->uuid,
                ],
                'gateway_response' => $data['gateway_response'] ?? null,
                'last_transaction' => $data['last_transaction'] ?? null,
                'acquirer_return_code' => $data['acquirer_return_code'] ?? null,
                'acquirer_message' => $data['acquirer_message'] ?? null,
                'qr_code' => $data['qr_code'] ?? null,
                'qr_code_url' => $data['qr_code_url'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ]);

            $event = $this->commercialStatus->sync($event->fresh(['organization', 'modules']));

            activity()
                ->performedOn($purchase)
                ->causedBy($order->buyer)
                ->withProperties([
                    'billing_order_id' => $order->id,
                    'event_id' => $event->id,
                    'package_id' => $package->id,
                    'journey' => 'public_event_checkout',
                ])
                ->log('Checkout publico confirmado e convertido em compra avulsa');

            return [
                'order' => $billingDocuments['order'],
                'payment' => $billingDocuments['payment'],
                'invoice' => $billingDocuments['invoice'],
                'purchase' => $purchase->fresh(),
                'event' => $event->load(['organization', 'modules']),
                'package' => $package,
            ];
        });
    }
}
