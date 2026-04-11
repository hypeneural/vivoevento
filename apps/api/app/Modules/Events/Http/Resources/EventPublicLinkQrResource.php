<?php

namespace App\Modules\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPublicLinkQrResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this['event_id'],
            'link_key' => $this['link_key'],
            'link' => $this['link'],
            'effective_branding' => $this['effective_branding'],
            'config' => $this['config'],
            'config_source' => $this['config_source'],
            'has_saved_config' => $this['has_saved_config'],
            'updated_at' => $this['updated_at'],
            'assets' => $this['assets'],
        ];
    }
}
