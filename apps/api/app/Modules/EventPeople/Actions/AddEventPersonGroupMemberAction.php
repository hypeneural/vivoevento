<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Models\EventPersonGroupMembership;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class AddEventPersonGroupMemberAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(EventPersonGroup $group, User $user, array $payload): EventPersonGroupMembership
    {
        return DB::transaction(function () use ($group, $user, $payload): EventPersonGroupMembership {
            return EventPersonGroupMembership::query()->create([
                'event_id' => $group->event_id,
                'event_person_group_id' => $group->id,
                'event_person_id' => (int) $payload['event_person_id'],
                'role_label' => $payload['role_label'] ?? null,
                'source' => (string) ($payload['source'] ?? 'manual'),
                'confidence' => $payload['confidence'] ?? null,
                'status' => (string) ($payload['status'] ?? 'active'),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }
}
