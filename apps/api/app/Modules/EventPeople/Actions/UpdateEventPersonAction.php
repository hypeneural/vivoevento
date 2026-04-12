<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Enums\EventPersonType;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Support\EventPeopleStateMachine;
use App\Modules\Users\Models\User;

class UpdateEventPersonAction
{
    public function __construct(
        private readonly EventPeopleStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(EventPerson $person, User $user, array $payload): EventPerson
    {
        $updates = [];

        if (array_key_exists('display_name', $payload)) {
            $updates['display_name'] = trim((string) $payload['display_name']);
        }

        if (array_key_exists('type', $payload)) {
            $updates['type'] = EventPersonType::tryFrom((string) $payload['type'])?->value ?? null;
        }

        if (array_key_exists('side', $payload)) {
            $updates['side'] = EventPersonSide::tryFrom((string) $payload['side'])?->value ?? null;
        }

        if (array_key_exists('importance_rank', $payload)) {
            $updates['importance_rank'] = (int) ($payload['importance_rank'] ?? 0);
        }

        if (array_key_exists('notes', $payload)) {
            $updates['notes'] = $payload['notes'];
        }

        $updates['updated_by'] = $user->id;

        $targetStatus = null;

        if (array_key_exists('status', $payload)) {
            $targetStatus = EventPersonStatus::tryFrom((string) $payload['status']);
        }

        $person->fill($updates)->save();

        if ($targetStatus instanceof EventPersonStatus) {
            $this->stateMachine->transitionPerson($person, $targetStatus, [
                'updated_by' => $user->id,
            ]);
        }

        return $person->fresh();
    }
}
