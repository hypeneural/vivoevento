<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Actions;

use App\Modules\Events\Enums\EventType;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventBrandingResolver;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;
use App\Modules\Gallery\Support\GalleryModelMatrixRegistry;
use App\Modules\Gallery\Support\GalleryThemeTokenResolver;

class BuildPublicGalleryPayloadAction
{
    public function __construct(
        private readonly EventBrandingResolver $brandingResolver,
        private readonly GalleryBuilderSchemaRegistry $schemaRegistry,
        private readonly GalleryModelMatrixRegistry $modelMatrixRegistry,
        private readonly GalleryThemeTokenResolver $themeTokenResolver,
    ) {}

    /**
     * @return array{event: array<string, mixed>, experience: array<string, mixed>}
     */
    public function execute(Event $event): array
    {
        $branding = $this->brandingResolver->resolve($event);
        $selection = $this->selectionForEvent($event);
        $derived = $this->modelMatrixRegistry->derive(
            $selection['event_type_family'],
            $selection['style_skin'],
            $selection['behavior_profile'],
        );

        $experience = $this->schemaRegistry->baseExperience();
        $experience['published_version'] = null;
        $experience['model_matrix'] = $selection;
        $experience['theme_key'] = 'event-brand';
        $experience['layout_key'] = $derived['layout_key'];
        $experience['theme_tokens'] = $this->themeTokenResolver->resolve(
            'event-brand',
            $branding['primary_color'] ?? null,
            $branding['secondary_color'] ?? null,
        );
        $experience['page_schema']['blocks']['hero']['variant'] = $selection['event_type_family'];
        $experience['media_behavior']['grid']['layout'] = $derived['grid_layout'];
        $experience['media_behavior']['video']['mode'] = $derived['video_mode'];
        $experience['media_behavior']['video']['allow_inline_preview'] = $derived['video_mode'] === 'inline_preview';
        $experience['media_behavior']['interstitials']['enabled'] = $derived['interstitial_policy'] !== 'disabled';
        $experience['media_behavior']['interstitials']['policy'] = $derived['interstitial_policy'];

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'event_type' => $event->event_type?->value ?? $event->event_type,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'location_name' => $event->location_name,
                'description' => $event->description,
                'branding' => [
                    'logo_url' => $branding['logo_url'] ?? null,
                    'cover_image_url' => $branding['cover_image_url'] ?? null,
                    'primary_color' => $branding['primary_color'] ?? null,
                    'secondary_color' => $branding['secondary_color'] ?? null,
                    'source' => $branding['source'] ?? null,
                ],
                'public_url' => $event->publicGalleryUrl(),
            ],
            'experience' => $experience,
        ];
    }

    /**
     * @return array{event_type_family: string, style_skin: string, behavior_profile: string}
     */
    private function selectionForEvent(Event $event): array
    {
        $eventType = $event->event_type instanceof EventType
            ? $event->event_type
            : EventType::tryFrom((string) $event->event_type);

        return match ($eventType) {
            EventType::Corporate, EventType::Fair, EventType::Graduation => [
                'event_type_family' => 'corporate',
                'style_skin' => 'clean',
                'behavior_profile' => 'light',
            ],
            EventType::Fifteen, EventType::Birthday => [
                'event_type_family' => 'quince',
                'style_skin' => 'modern',
                'behavior_profile' => 'light',
            ],
            default => [
                'event_type_family' => 'wedding',
                'style_skin' => 'romantic',
                'behavior_profile' => 'light',
            ],
        };
    }
}
