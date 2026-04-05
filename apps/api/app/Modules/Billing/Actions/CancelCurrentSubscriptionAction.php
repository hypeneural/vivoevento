<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelCurrentSubscriptionAction
{
    public function execute(
        Organization $organization,
        ?User $actor = null,
        string $effective = 'period_end',
        ?string $reason = null,
    ): Subscription {
        return DB::transaction(function () use ($organization, $actor, $effective, $reason) {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()
                ->where('organization_id', $organization->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                throw ValidationException::withMessages([
                    'subscription' => ['Nenhuma assinatura encontrada para a organizacao atual.'],
                ]);
            }

            if (in_array($subscription->status, ['canceled', 'expired'], true)) {
                if ($subscription->status === 'canceled' && $effective === 'immediately' && $subscription->isCanceledPendingEnd()) {
                    $subscription->forceFill([
                        'ends_at' => now(),
                        'renews_at' => null,
                    ])->save();
                }

                return $subscription->fresh(['plan.features']);
            }

            $canceledAt = now();
            $endsAt = $effective === 'immediately'
                ? $canceledAt->copy()
                : $this->resolvePeriodEnd($subscription, $canceledAt);

            $subscription->forceFill([
                'status' => 'canceled',
                'canceled_at' => $subscription->canceled_at ?? $canceledAt,
                'ends_at' => $endsAt,
                'renews_at' => $effective === 'immediately' ? null : $subscription->renews_at,
            ])->save();

            activity()
                ->performedOn($subscription)
                ->causedBy($actor)
                ->withProperties([
                    'organization_id' => $organization->id,
                    'reason' => $reason,
                    'effective' => $effective,
                    'previous_status' => $subscription->getOriginal('status'),
                    'effective_status' => 'canceled',
                    'ends_at' => $subscription->ends_at?->toISOString(),
                ])
                ->log('Assinatura da conta cancelada');

            return $subscription->fresh(['plan.features']);
        });
    }

    private function resolvePeriodEnd(Subscription $subscription, Carbon $reference): Carbon
    {
        $candidates = collect([
            $subscription->renews_at,
            $subscription->trial_ends_at,
            $subscription->ends_at,
        ])->filter(fn ($candidate) => $candidate instanceof Carbon && $candidate->greaterThan($reference));

        return ($candidates->sortBy(fn (Carbon $candidate) => $candidate->getTimestamp())->first() ?? $reference)->copy();
    }
}
