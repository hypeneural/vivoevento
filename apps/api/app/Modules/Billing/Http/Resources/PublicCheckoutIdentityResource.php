<?php

namespace App\Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCheckoutIdentityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'identity_status' => $this['identity_status'] ?? null,
            'title' => $this['title'] ?? null,
            'description' => $this['description'] ?? null,
            'action_label' => $this['action_label'] ?? null,
            'login_url' => $this['login_url'] ?? null,
            'cooldown_seconds' => $this['cooldown_seconds'] ?? null,
        ];
    }
}
