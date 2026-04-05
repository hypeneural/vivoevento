<?php

namespace App\Modules\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventCommercialStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this['event_id'],
            'commercial_mode' => $this['commercial_mode'],
            'subscription_summary' => $this['subscription_summary'],
            'purchase_summary' => $this['purchase_summary'],
            'grants_summary' => $this['grants_summary'] ?? [],
            'event_modules' => $this['event_modules'],
            'resolved_entitlements' => $this['resolved_entitlements'],
        ];
    }
}
