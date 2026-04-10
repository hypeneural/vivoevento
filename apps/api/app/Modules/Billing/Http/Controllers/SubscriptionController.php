<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\CancelCurrentSubscriptionAction;
use App\Modules\Billing\Actions\CreateSubscriptionCheckoutAction;
use App\Modules\Billing\Actions\ListSubscriptionWalletCardsAction;
use App\Modules\Billing\Actions\ReconcileRecurringSubscriptionAction;
use App\Modules\Billing\Actions\UpdateSubscriptionCardAction;
use App\Modules\Billing\Http\Requests\CancelCurrentSubscriptionRequest;
use App\Modules\Billing\Http\Requests\StoreSubscriptionCheckoutRequest;
use App\Modules\Billing\Http\Requests\UpdateSubscriptionCardRequest;
use App\Modules\Billing\Http\Resources\BillingInvoiceResource;
use App\Modules\Billing\Models\Subscription;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseController
{
    /**
     * GET /api/v1/billing/subscription
     */
    public function current(Request $request): JsonResponse
    {
        $this->ensureCanViewBilling($request);

        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $subscription = $org->subscription;

        if (! $subscription) {
            return $this->success(null);
        }

        $subscription->load('plan.features');

        return $this->success($this->serializeSubscription($subscription));
    }

    /**
     * GET /api/v1/plans/current
     */
    public function currentPlan(Request $request): JsonResponse
    {
        $this->ensureCanViewBilling($request);

        $org = $request->user()->currentOrganization();
        $plan = $org?->subscription?->plan;

        if (! $plan) {
            return $this->success(null);
        }

        $plan->load(['prices', 'features']);

        return $this->success($plan);
    }

    /**
     * GET /api/v1/billing/invoices
     */
    public function invoices(Request $request): JsonResponse
    {
        $this->ensureCanViewBilling($request);

        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $invoices = $org->invoices()
            ->with([
                'order.event:id,title',
                'order.payments',
                'payments',
                'subscription.plan:id,code,name,audience,description',
                'subscriptionCycle',
            ])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->paginated(BillingInvoiceResource::collection($invoices));
    }

    /**
     * POST /api/v1/billing/checkout
     */
    public function checkout(
        StoreSubscriptionCheckoutRequest $request,
        CreateSubscriptionCheckoutAction $action,
    ): JsonResponse {
        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $validated = $request->validated();
        $plan = \App\Modules\Plans\Models\Plan::findOrFail($validated['plan_id']);
        $checkout = $action->execute(
            $org,
            $request->user(),
            $plan,
            $validated['billing_cycle'] ?? 'monthly',
            $validated,
        );

        $subscription = $checkout['subscription'];
        $order = $checkout['order'];
        $payment = $checkout['payment'];
        $invoice = $checkout['invoice'];

        return $this->created([
            'subscription_id' => $subscription?->id,
            'plan_name' => $plan->name,
            'status' => $subscription?->status ?? $order->status?->value,
            'starts_at' => $subscription?->starts_at?->toISOString(),
            'renews_at' => $subscription?->renews_at?->toISOString(),
            'billing_order_id' => $order->id,
            'payment_id' => $payment?->id,
            'invoice_id' => $invoice?->id,
            'checkout' => [
                'provider' => $order->gateway_provider,
                'gateway_order_id' => $order->gateway_order_id,
                'status' => $order->status?->value,
                'checkout_url' => $order->metadata_json['gateway']['checkout_url'] ?? null,
                'confirm_url' => $order->metadata_json['gateway']['confirm_url'] ?? null,
                'expires_at' => $order->metadata_json['gateway']['expires_at'] ?? null,
            ],
        ]);
    }

    public function cancel(
        CancelCurrentSubscriptionRequest $request,
        CancelCurrentSubscriptionAction $action,
    ): JsonResponse {
        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $subscription = $action->execute(
            $org,
            $request->user(),
            $request->validated('effective', 'period_end'),
            $request->validated('reason'),
        );

        return $this->success([
            'message' => $request->validated('effective', 'period_end') === 'immediately'
                ? 'Assinatura da conta cancelada com efeito imediato.'
                : 'Assinatura da conta agendada para cancelamento ao fim do ciclo.',
            'cancel_effective' => $request->validated('effective', 'period_end'),
            'access_until' => $subscription->ends_at?->toISOString(),
            'subscription' => $this->serializeSubscription($subscription),
        ]);
    }

    public function cards(Request $request, ListSubscriptionWalletCardsAction $action): JsonResponse
    {
        $this->ensureCanViewBilling($request);

        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        return $this->success($action->execute($org));
    }

    public function updateCard(
        UpdateSubscriptionCardRequest $request,
        UpdateSubscriptionCardAction $action,
    ): JsonResponse {
        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $subscription = $action->execute($org, $request->validated(), $request->user());

        return $this->success([
            'message' => 'Cartao da assinatura atualizado.',
            'subscription' => $this->serializeSubscription($subscription),
        ]);
    }

    public function reconcile(Request $request, ReconcileRecurringSubscriptionAction $action): JsonResponse
    {
        $this->ensureCanManageBilling($request);

        $org = $request->user()->currentOrganization();

        if (! $org) {
            return $this->error('Nenhuma organizacao encontrada', 404);
        }

        $subscription = Subscription::query()
            ->where('organization_id', $org->id)
            ->latest('id')
            ->first();

        if (! $subscription) {
            return $this->error('Nenhuma assinatura encontrada para a organizacao atual.', 404);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'with_charge_details' => ['nullable', 'boolean'],
        ]);

        $summary = $action->execute($subscription, $validated);
        $summary['subscription'] = $this->serializeSubscription($summary['subscription']);

        return $this->success($summary);
    }

    public function index(): JsonResponse
    {
        return $this->success(Subscription::with('plan')->latest()->paginate(20));
    }

    public function show(Subscription $subscription): JsonResponse
    {
        return $this->success($subscription->load(['plan', 'organization']));
    }

    private function serializeSubscription(Subscription $subscription): array
    {
        $cancelAtPeriodEnd = $subscription->isCanceledPendingEnd();

        return [
            'id' => $subscription->id,
            'plan_key' => $subscription->plan?->code,
            'plan_name' => $subscription->plan?->name,
            'billing_cycle' => $subscription->billing_cycle,
            'status' => $subscription->status,
            'contract_status' => $subscription->contract_status,
            'billing_status' => $subscription->billing_status,
            'access_status' => $subscription->access_status,
            'payment_method' => $subscription->payment_method,
            'starts_at' => $subscription->starts_at?->toISOString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            'current_period_started_at' => $subscription->current_period_started_at?->toISOString(),
            'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
            'renews_at' => $subscription->renews_at?->toISOString(),
            'next_billing_at' => $subscription->next_billing_at?->toISOString(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'canceled_at' => $subscription->canceled_at?->toISOString(),
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'cancellation_effective_at' => $cancelAtPeriodEnd ? $subscription->ends_at?->toISOString() : $subscription->canceled_at?->toISOString(),
            'gateway_provider' => $subscription->gateway_provider,
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'gateway_customer_id' => $subscription->gateway_customer_id,
            'gateway_card_id' => $subscription->gateway_card_id,
            'features' => $subscription->plan?->features?->pluck('feature_value', 'feature_key')->all() ?? [],
        ];
    }

    private function ensureCanViewBilling(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user
                && (
                    $user->can('billing.view')
                    || $user->can('billing.manage')
                    || $user->can('billing.purchase')
                    || $user->can('billing.manage_subscription')
                ),
            403
        );
    }

    private function ensureCanManageBilling(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user
                && (
                    $user->can('billing.manage')
                    || $user->can('billing.manage_subscription')
                ),
            403
        );
    }
}
