<?php

namespace App\Modules\Organizations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role_key' => $this->role_key,
            'is_owner' => (bool) $this->is_owner,
            'status' => $this->status,
            'invited_at' => $this->invited_at?->toISOString(),
            'joined_at' => $this->joined_at?->toISOString(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'avatar_path' => $this->user->avatar_path,
            ] : null,
        ];
    }
}
