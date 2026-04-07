<?php

namespace Database\Factories;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Telegram\Models\TelegramInboxSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class TelegramInboxSessionFactory extends Factory
{
    protected $model = TelegramInboxSession::class;

    public function definition(): array
    {
        $event = Event::factory()->active()->create();
        $channel = EventChannel::query()->create([
            'event_id' => $event->id,
            'channel_type' => ChannelType::TelegramBot->value,
            'provider' => 'telegram',
            'external_id' => fake()->bothify('EVT####'),
            'label' => 'Telegram',
            'status' => 'active',
            'config_json' => [
                'bot_username' => 'eventovivoBot',
                'session_ttl_minutes' => 180,
            ],
        ]);

        return [
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'event_channel_id' => $channel->id,
            'chat_external_id' => (string) fake()->numberBetween(100000, 999999),
            'sender_external_id' => (string) fake()->numberBetween(100000, 999999),
            'sender_name' => fake()->firstName(),
            'status' => 'active',
            'activated_by_provider_message_id' => (string) fake()->numberBetween(1, 1000),
            'last_inbound_provider_message_id' => (string) fake()->numberBetween(1, 1000),
            'activated_at' => now(),
            'last_interaction_at' => now(),
            'expires_at' => now()->addMinutes(180),
            'closed_at' => null,
            'metadata_json' => [],
        ];
    }
}
