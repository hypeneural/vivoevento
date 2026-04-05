<?php

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Events\Http\Resources\EventCommercialStatusResource;
use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Organizations\Http\Resources\OrganizationResource;
use App\Modules\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTrialEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'message' => $this['message'],
            'token' => $this['token'],
            'user' => new UserResource($this['user']),
            'organization' => new OrganizationResource($this['organization']),
            'event' => new EventResource($this['event']),
            'commercial_status' => new EventCommercialStatusResource($this['commercial_status']),
            'trial' => $this['trial'],
            'onboarding' => $this['onboarding'],
        ];
    }
}
