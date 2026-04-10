<?php

namespace Database\Factories;

use App\Modules\EventTeam\Models\EventTeamInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventTeamInvitationFactory extends Factory
{
    protected $model = EventTeamInvitation::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'organization_id' => OrganizationFactory::new(),
            'invited_by' => UserFactory::new(),
            'existing_user_id' => null,
            'accepted_user_id' => null,
            'invitee_name' => fake()->name(),
            'invitee_email' => fake()->safeEmail(),
            'invitee_phone' => '55' . fake()->numerify('119########'),
            'preset_key' => 'event.media-viewer',
            'persisted_role' => 'viewer',
            'delivery_channel' => 'manual',
            'delivery_status' => 'manual_link',
            'delivery_error' => null,
            'token' => Str::random(64),
            'token_expires_at' => now()->addDays(7),
            'invitation_url' => 'https://app.eventovivo.test/convites/eventos/' . Str::random(64),
            'status' => EventTeamInvitation::STATUS_PENDING,
            'accepted_at' => null,
            'revoked_at' => null,
            'last_sent_at' => null,
        ];
    }
}
