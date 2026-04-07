<?php

namespace App\Modules\Partners\Http\Resources;

use App\Modules\Organizations\Models\OrganizationMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerStaffMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OrganizationMember $member */
        $member = $this->resource;

        return [
            'id' => $member->id,
            'role_key' => $member->role_key,
            'is_owner' => (bool) $member->is_owner,
            'status' => $member->status,
            'invited_at' => $member->invited_at?->toISOString(),
            'joined_at' => $member->joined_at?->toISOString(),
            'user' => $member->user ? [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'phone' => $member->user->phone,
            ] : null,
        ];
    }
}
