<?php

namespace Database\Factories;

use App\Modules\Events\Models\Event;
use App\Modules\WhatsApp\Models\WhatsAppInboxSession;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppInboxSessionFactory extends Factory
{
    protected $model = WhatsAppInboxSession::class;

    public function definition(): array
    {
        $organization = OrganizationFactory::new();

        return [
            'organization_id' => $organization,
            'event_id' => Event::factory()->active()->state(fn () => [
                'organization_id' => $organization,
            ]),
            'event_channel_id' => null,
            'instance_id' => WhatsAppInstance::factory()->connected()->state(fn () => [
                'organization_id' => $organization,
            ]),
            'sender_external_id' => fake()->numerify('55###########'),
            'sender_phone' => fake()->numerify('55###########'),
            'sender_lid' => fake()->numerify('###########') . '@lid',
            'sender_name' => fake()->name(),
            'chat_external_id' => fake()->numerify('55###########'),
            'status' => 'active',
            'activated_by_provider_message_id' => strtoupper(fake()->bothify('3EB0###########')),
            'last_inbound_provider_message_id' => strtoupper(fake()->bothify('3EB0###########')),
            'activated_at' => now()->subMinutes(5),
            'last_interaction_at' => now()->subMinute(),
            'expires_at' => now()->addMinutes(175),
            'metadata_json' => [],
        ];
    }
}
