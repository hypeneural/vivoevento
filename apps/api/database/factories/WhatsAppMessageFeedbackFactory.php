<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppMessageFeedbackFactory extends Factory
{
    protected $model = WhatsAppMessageFeedback::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'instance_id' => WhatsAppInstanceFactory::new(),
            'inbound_provider_message_id' => fake()->regexify('[A-Z0-9]{20}'),
            'chat_external_id' => fake()->numerify('55###########'),
            'sender_external_id' => fake()->numerify('55###########'),
            'feedback_kind' => 'reaction',
            'feedback_phase' => 'detected',
            'status' => 'sent',
            'reaction_emoji' => '⏳',
            'reply_text' => null,
            'error_message' => null,
            'attempted_at' => now(),
            'completed_at' => now(),
        ];
    }
}
