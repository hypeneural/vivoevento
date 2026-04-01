<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_path' => $this->avatar_path,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'organizations' => $this->whenLoaded('organizations', fn () =>
                $this->organizations->map(fn ($org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'role_key' => $org->pivot->role_key,
                    'is_owner' => $org->pivot->is_owner,
                ])
            ),
        ];
    }
}
