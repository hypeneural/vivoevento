<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateEventPersonGroupAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(EventPersonGroup $group, User $user, array $payload): EventPersonGroup
    {
        return DB::transaction(function () use ($group, $user, $payload): EventPersonGroup {
            $group->forceFill([
                'display_name' => array_key_exists('display_name', $payload) ? trim((string) $payload['display_name']) : $group->display_name,
                'group_type' => array_key_exists('group_type', $payload) ? ((trim((string) ($payload['group_type'] ?? '')) ?: 'custom')) : $group->group_type,
                'side' => array_key_exists('side', $payload)
                    ? (EventPersonSide::tryFrom((string) ($payload['side'] ?? EventPersonSide::Neutral->value)) ?? EventPersonSide::Neutral)->value
                    : ($group->side?->value ?? $group->side),
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $group->notes,
                'importance_rank' => array_key_exists('importance_rank', $payload) ? (int) ($payload['importance_rank'] ?? 0) : $group->importance_rank,
                'status' => array_key_exists('status', $payload) ? (string) ($payload['status'] ?? 'active') : $group->status,
                'updated_by' => $user->id,
            ])->save();

            return $group->refresh();
        });
    }
}
