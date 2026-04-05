<?php

namespace App\Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $organization = $this->organization;
        $subscription = $organization?->subscription;
        $plan = $subscription?->plan;

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'type' => $this->type?->value,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'document_number' => $this->document_number,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'events_count' => $this->whenCounted('events'),
            'organization_name' => $organization?->displayName(),
            'organization_slug' => $organization?->slug,
            'organization_status' => $organization?->status?->value,
            'organization_billing' => $subscription ? [
                'source' => 'organization_subscription',
                'plan_key' => $plan?->code,
                'plan_name' => $plan?->name,
                'subscription_status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'starts_at' => $subscription->starts_at?->toISOString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'renews_at' => $subscription->renews_at?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
            ] : null,
            'plan_key' => $plan?->code,
            'plan_name' => $plan?->name,
            'subscription_status' => $subscription?->status,
        ];
    }
}
