<?php

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Events\Http\Resources\EventCommercialStatusResource;
use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Organizations\Http\Resources\OrganizationResource;
use App\Modules\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicEventCheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'message' => $this['message'] ?? null,
            'token' => $this['token'] ?? null,
            'user' => isset($this['user']) && $this['user'] !== null
                ? new UserResource($this['user'])
                : null,
            'organization' => isset($this['organization']) && $this['organization'] !== null
                ? new OrganizationResource($this['organization'])
                : null,
            'event' => isset($this['event']) && $this['event'] !== null
                ? new EventResource($this['event'])
                : null,
            'commercial_status' => isset($this['commercial_status']) && $this['commercial_status'] !== null
                ? new EventCommercialStatusResource($this['commercial_status'])
                : null,
            'checkout' => $this['checkout'] ?? null,
            'purchase' => $this['purchase'] ?? null,
            'onboarding' => $this['onboarding'] ?? null,
        ];
    }
}
