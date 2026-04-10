<?php

namespace App\Modules\EventTeam\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Models\EventTeamMember;
use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class UpsertEventTeamMemberAction
{
    public function __construct(
        private readonly EventAccessPresetRegistry $presetRegistry,
    ) {}

    public function execute(Event $event, array $data, User $actor, ?EventTeamMember $member = null): EventTeamMember
    {
        return DB::transaction(function () use ($event, $data, $actor, $member) {
            $resolvedRole = $this->resolveRole($data);
            $user = User::query()->findOrFail((int) $data['user_id']);

            if (! $user->roles()->exists()) {
                $user->assignRole('viewer');
            }

            $member = $member
                ? tap($member)->update(['role' => $resolvedRole])
                : EventTeamMember::query()->updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'role' => $resolvedRole,
                    ],
                );

            activity()
                ->event('event.team.member.synced')
                ->performedOn($event)
                ->causedBy($actor)
                ->withProperties([
                    'event_id' => $event->id,
                    'member_id' => $member->id,
                    'user_id' => $user->id,
                    'role' => $resolvedRole,
                ])
                ->log('Membro sincronizado na equipe do evento');

            return $member->fresh(['user:id,name,email,phone,avatar_path']);
        });
    }

    private function resolveRole(array $data): string
    {
        if (filled($data['preset_key'] ?? null)) {
            return $this->presetRegistry->persistedRoleForPresetKey((string) $data['preset_key']);
        }

        return (string) ($data['role'] ?? 'viewer');
    }
}
