<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateEventPersonGroupAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Event $event, User $user, array $payload): EventPersonGroup
    {
        return DB::transaction(function () use ($event, $user, $payload): EventPersonGroup {
            $displayName = trim((string) $payload['display_name']);
            $side = EventPersonSide::tryFrom((string) ($payload['side'] ?? EventPersonSide::Neutral->value)) ?? EventPersonSide::Neutral;

            return EventPersonGroup::query()->create([
                'event_id' => $event->id,
                'display_name' => $displayName,
                'slug' => $this->uniqueSlug($event->id, $displayName),
                'group_type' => trim((string) ($payload['group_type'] ?? 'custom')) ?: 'custom',
                'side' => $side->value,
                'notes' => $payload['notes'] ?? null,
                'importance_rank' => (int) ($payload['importance_rank'] ?? 0),
                'status' => (string) ($payload['status'] ?? 'active'),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    private function uniqueSlug(int $eventId, string $displayName): string
    {
        $base = Str::slug($displayName);
        $slug = $base !== '' ? $base : 'grupo';
        $candidate = $slug;
        $suffix = 2;

        while (EventPersonGroup::query()->where('event_id', $eventId)->where('slug', $candidate)->exists()) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
