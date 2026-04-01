<?php
namespace App\Modules\Billing\Http\Controllers;
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
        $org = $request->user()->currentOrganization();

        if (!$org) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        $subscription = $org->subscription;

        if (!$subscription) {
            return $this->success(null);
        }

        $subscription->load('plan.features');

        return $this->success([
            'id' => $subscription->id,
            'plan_key' => $subscription->plan?->slug,
            'plan_name' => $subscription->plan?->name,
            'billing_cycle' => $subscription->billing_cycle,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at?->toISOString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            'renews_at' => $subscription->renews_at?->toISOString(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'canceled_at' => $subscription->canceled_at?->toISOString(),
            'features' => $subscription->plan?->features?->pluck('value', 'key'),
        ]);
    }

    /**
     * GET /api/v1/plans/current
     */
    public function currentPlan(Request $request): JsonResponse
    {
        $org = $request->user()->currentOrganization();
        $plan = $org?->subscription?->plan;

        if (!$plan) {
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
        $org = $request->user()->currentOrganization();

        if (!$org) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        // TODO: integrate with payment gateway for real invoices
        $purchases = $org->purchases()
            ->with('plan:id,name,slug')
            ->latest()
            ->paginate(20);

        return $this->success($purchases);
    }

    /**
     * POST /api/v1/billing/checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $org = $request->user()->currentOrganization();

        if (!$org) {
            return $this->error('Nenhuma organização encontrada', 404);
        }

        // TODO: integrate with payment gateway (Stripe/Pagar.me)
        // For now, create the subscription directly
        $plan = \App\Modules\Plans\Models\Plan::findOrFail($validated['plan_id']);

        $subscription = Subscription::updateOrCreate(
            ['organization_id' => $org->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $validated['billing_cycle'] ?? 'monthly',
                'starts_at' => now(),
                'renews_at' => now()->addMonth(),
            ]
        );

        activity()
            ->performedOn($subscription)
            ->causedBy($request->user())
            ->withProperties(['plan_id' => $plan->id, 'plan_name' => $plan->name])
            ->log('Plano contratado: ' . $plan->name);

        return $this->created([
            'subscription_id' => $subscription->id,
            'plan_name' => $plan->name,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at?->toISOString(),
            'renews_at' => $subscription->renews_at?->toISOString(),
        ]);
    }

    // ─── Admin ────────────────────────────────────────────

    public function index(): JsonResponse
    {
        return $this->success(Subscription::with('plan')->latest()->paginate(20));
    }

    public function show(Subscription $subscription): JsonResponse
    {
        return $this->success($subscription->load(['plan', 'organization']));
    }
}
