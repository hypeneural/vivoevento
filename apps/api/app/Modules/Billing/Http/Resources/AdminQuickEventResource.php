<?php

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Events\Http\Resources\EventCommercialStatusResource;
use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Organizations\Http\Resources\OrganizationResource;
use App\Modules\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminQuickEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'message' => $this['message'] ?? null,
            'responsible_user' => isset($this['responsible_user']) && $this['responsible_user'] !== null
                ? new UserResource($this['responsible_user'])
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
            'grant' => $this['grant'] ?? null,
            'setup' => $this['setup'] ?? null,
            'access_delivery' => $this['access_delivery'] ?? null,
            'onboarding' => $this['onboarding'] ?? null,
        ];
    }
}
