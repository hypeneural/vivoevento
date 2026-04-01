<?php

namespace Database\Factories;

use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMediaFactory extends Factory
{
    protected $model = EventMedia::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'media_type' => 'image',
            'source_type' => 'channel',
            'source_label' => 'WhatsApp',
            'original_filename' => fake()->lexify('IMG_??????.jpg'),
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(100000, 5000000),
            'width' => fake()->randomElement([1920, 1080, 3840]),
            'height' => fake()->randomElement([1080, 1920, 2160]),
            'processing_status' => MediaProcessingStatus::Received->value,
            'moderation_status' => ModerationStatus::Pending->value,
            'publication_status' => PublicationStatus::Draft->value,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'moderation_status' => ModerationStatus::Approved->value,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'moderation_status' => ModerationStatus::Approved->value,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now(),
        ]);
    }
}
