<?php

namespace App\Modules\Gallery\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Models\GalleryPreset;
use App\Modules\Gallery\Support\GalleryModelMatrixRegistry;
use App\Modules\Users\Models\User;
use Illuminate\Support\Str;

class CreateGalleryPresetAction
{
    public function __construct(
        private readonly GalleryModelMatrixRegistry $matrixRegistry,
    ) {}

    public function execute(
        User $user,
        int $organizationId,
        array $payload,
        ?Event $sourceEvent = null,
        ?EventGallerySetting $sourceSettings = null,
    ): GalleryPreset {
        $slugBase = Str::slug((string) $payload['name']);
        $slug = $slugBase !== '' ? $slugBase : 'gallery-preset';
        $suffix = 1;

        while (GalleryPreset::query()
            ->where('organization_id', $organizationId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$slugBase}-{$suffix}";
            $suffix++;
        }

        $eventTypeFamily = $payload['event_type_family'] ?? $sourceSettings?->event_type_family ?? 'wedding';
        $styleSkin = $payload['style_skin'] ?? $sourceSettings?->style_skin ?? 'romantic';
        $behaviorProfile = $payload['behavior_profile'] ?? $sourceSettings?->behavior_profile ?? 'light';
        $derived = $this->matrixRegistry->derive($eventTypeFamily, $styleSkin, $behaviorProfile);

        return GalleryPreset::query()->create([
            'organization_id' => $organizationId,
            'created_by' => $user->id,
            'source_event_id' => $sourceEvent?->id,
            'name' => $payload['name'],
            'slug' => $slug,
            'description' => $payload['description'] ?? null,
            'event_type_family' => $eventTypeFamily,
            'style_skin' => $styleSkin,
            'behavior_profile' => $behaviorProfile,
            'theme_key' => $payload['theme_key'] ?? $sourceSettings?->theme_key ?? 'event-brand',
            'layout_key' => $payload['layout_key'] ?? $sourceSettings?->layout_key ?? 'editorial-masonry',
            'theme_tokens_json' => $payload['theme_tokens'] ?? $sourceSettings?->theme_tokens_json ?? [],
            'page_schema_json' => $payload['page_schema'] ?? $sourceSettings?->page_schema_json ?? [],
            'media_behavior_json' => $payload['media_behavior'] ?? $sourceSettings?->media_behavior_json ?? [],
            'derived_preset_key' => $payload['derived_preset_key'] ?? $derived['derived_preset_key'],
        ]);
    }
}
