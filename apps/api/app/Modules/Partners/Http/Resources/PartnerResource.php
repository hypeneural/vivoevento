<?php

namespace App\Modules\Partners\Http\Resources;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Organization $partner */
        $partner = $this->resource;
        $stats = $partner->relationLoaded('partnerStats') ? $partner->getRelation('partnerStats') : null;
        $profile = $partner->relationLoaded('partnerProfile') ? $partner->getRelation('partnerProfile') : null;
        $subscription = $partner->relationLoaded('subscription') ? $partner->getRelation('subscription') : $partner->subscription;
        $plan = $subscription?->plan;
        $ownerMembership = $this->resolveOwnerMembership($partner);

        return [
            'id' => $partner->id,
            'uuid' => $partner->uuid,
            'type' => $partner->type?->value ?? $partner->type,
            'name' => $partner->name,
            'legal_name' => $partner->legal_name,
            'trade_name' => $partner->trade_name,
            'document_number' => $partner->document_number,
            'slug' => $partner->slug,
            'email' => $partner->email,
            'billing_email' => $partner->billing_email,
            'phone' => $partner->phone,
            'logo_path' => $partner->logo_path,
            'timezone' => $partner->timezone,
            'status' => $partner->status?->value ?? $partner->status,
            'segment' => $profile?->segment,
            'notes' => $profile?->notes,
            'clients_count' => (int) ($stats?->clients_count ?? 0),
            'events_count' => (int) ($stats?->events_count ?? 0),
            'active_events_count' => (int) ($stats?->active_events_count ?? 0),
            'team_size' => (int) ($stats?->team_size ?? 0),
            'active_bonus_grants_count' => (int) ($stats?->active_bonus_grants_count ?? 0),
            'current_subscription' => [
                'plan_key' => $stats?->subscription_plan_code ?? $plan?->code,
                'plan_name' => $stats?->subscription_plan_name ?? $plan?->name,
                'status' => $stats?->subscription_status ?? $subscription?->status,
                'billing_cycle' => $stats?->subscription_billing_cycle ?? $subscription?->billing_cycle,
            ],
            'revenue' => [
                'currency' => 'BRL',
                'subscription_cents' => (int) ($stats?->subscription_revenue_cents ?? 0),
                'event_package_cents' => (int) ($stats?->event_package_revenue_cents ?? 0),
                'total_cents' => (int) ($stats?->total_revenue_cents ?? 0),
            ],
            'stats_refreshed_at' => $stats?->refreshed_at?->toISOString(),
            'owner' => $ownerMembership ? [
                'id' => $ownerMembership->user?->id,
                'name' => $ownerMembership->user?->name,
                'email' => $ownerMembership->user?->email,
                'phone' => $ownerMembership->user?->phone,
            ] : null,
            'created_at' => $partner->created_at?->toISOString(),
            'updated_at' => $partner->updated_at?->toISOString(),
        ];
    }

    private function resolveOwnerMembership(Organization $partner): ?OrganizationMember
    {
        if (! $partner->relationLoaded('members')) {
            return null;
        }

        return $partner->members
            ->first(fn (OrganizationMember $member) => $member->is_owner && $member->status === 'active');
    }
}
