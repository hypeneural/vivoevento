<?php

namespace Database\Factories;

use App\Modules\Organizations\Models\OrganizationMemberInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationMemberInvitationFactory extends Factory
{
    protected $model = OrganizationMemberInvitation::class;

    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'organization_id' => OrganizationFactory::new(),
            'invited_by' => UserFactory::new(),
            'existing_user_id' => null,
            'accepted_user_id' => null,
            'invitee_name' => fake()->name(),
            'invitee_email' => fake()->safeEmail(),
            'invitee_phone' => '55' . fake()->numerify('119########'),
            'role_key' => 'partner-manager',
            'delivery_channel' => 'manual',
            'delivery_status' => 'manual_link',
            'delivery_error' => null,
            'token' => $token,
            'token_expires_at' => now()->addDays(7),
            'invitation_url' => 'https://app.eventovivo.test/convites/equipe/' . $token,
            'status' => OrganizationMemberInvitation::STATUS_PENDING,
            'accepted_at' => null,
            'revoked_at' => null,
            'last_sent_at' => null,
        ];
    }
}
