<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventBrandingResolver;
use App\Modules\Gallery\Support\GalleryBuilderAssetUrlResolver;
use App\Modules\Gallery\Support\GalleryBuilderPresetRegistry;

class BuildPublicGalleryPayloadAction
{
    public function __construct(
        private readonly EventBrandingResolver $brandingResolver,
        private readonly GalleryBuilderPresetRegistry $presetRegistry,
        private readonly GalleryBuilderAssetUrlResolver $assetUrlResolver,
    ) {}

    /**
     * @param  array<string, mixed>|null  $storedSettings
     * @return array{event: array<string, mixed>, experience: array<string, mixed>}
     */
    public function execute(Event $event, ?array $storedSettings = null, ?int $publishedVersion = null): array
    {
        $branding = $this->brandingResolver->resolve($event);
        $experience = $this->presetRegistry->normalize($event, $storedSettings);
        $experience['page_schema'] = $this->assetUrlResolver->hydratePageSchema(
            is_array($experience['page_schema'] ?? null) ? $experience['page_schema'] : [],
        );
        $experience['version'] = 1;
        $experience['published_version'] = $publishedVersion;
        $experience['model_matrix'] = [
            'event_type_family' => $experience['event_type_family'],
            'style_skin' => $experience['style_skin'],
            'behavior_profile' => $experience['behavior_profile'],
        ];
        unset(
            $experience['event_type_family'],
            $experience['style_skin'],
            $experience['behavior_profile'],
            $experience['derived_preset_key'],
            $experience['is_enabled'],
        );

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
}
