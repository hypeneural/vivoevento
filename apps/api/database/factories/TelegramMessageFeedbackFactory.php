<?php

namespace Database\Factories;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Telegram\Models\TelegramMessageFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

class TelegramMessageFeedbackFactory extends Factory
{
    protected $model = TelegramMessageFeedback::class;

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
            'config_json' => [],
        ]);

        return [
            'event_id' => $event->id,
            'event_channel_id' => $channel->id,
            'trace_id' => null,
            'inbound_provider_message_id' => (string) fake()->numberBetween(1, 9999),
            'chat_external_id' => (string) fake()->numberBetween(100000, 999999),
            'sender_external_id' => (string) fake()->numberBetween(100000, 999999),
            'feedback_kind' => 'reaction',
            'feedback_phase' => 'detected',
            'status' => 'sent',
            'reaction_emoji' => "\u{23F3}",
            'resolution_json' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ];
    }
}
