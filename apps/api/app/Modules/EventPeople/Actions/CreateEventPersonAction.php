<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Enums\EventPersonType;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateEventPersonAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Event $event, User $user, array $payload): EventPerson
    {
        return DB::transaction(function () use ($event, $user, $payload): EventPerson {
            $displayName = trim((string) $payload['display_name']);
            $type = EventPersonType::tryFrom((string) ($payload['type'] ?? EventPersonType::Guest->value)) ?? EventPersonType::Guest;
            $side = EventPersonSide::tryFrom((string) ($payload['side'] ?? EventPersonSide::Neutral->value)) ?? EventPersonSide::Neutral;
            $status = EventPersonStatus::tryFrom((string) ($payload['status'] ?? EventPersonStatus::Active->value)) ?? EventPersonStatus::Active;

            return EventPerson::query()->create([
                'event_id' => $event->id,
                'display_name' => $displayName,
                'slug' => $this->uniqueSlug($event->id, $displayName),
                'type' => $type->value,
                'side' => $side->value,
                'importance_rank' => (int) ($payload['importance_rank'] ?? 0),
                'notes' => $payload['notes'] ?? null,
                'status' => $status->value,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    private function uniqueSlug(int $eventId, string $displayName): string
    {
        $base = Str::slug($displayName);
        $slug = $base !== '' ? $base : 'pessoa';
        $candidate = $slug;
        $suffix = 2;

        while (EventPerson::query()->where('event_id', $eventId)->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
