<?php

namespace App\Modules\Gallery\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Support\GalleryBuilderPresetRegistry;
use App\Modules\Users\Models\User;

class UpdateEventGallerySettingsAction
{
    public function __construct(
        private readonly GalleryBuilderPresetRegistry $registry,
    ) {}

    public function execute(
        Event $event,
        EventGallerySetting $settings,
        array $data,
        ?User $user = null,
    ): EventGallerySetting {
        $normalized = $this->registry->normalize(
            $event,
            array_replace_recursive($settings->toBuilderPayload(), $data),
        );

        $this->registry->assertAccessible($normalized['theme_tokens']);

        $settings->fill([
            'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : $settings->is_enabled,
            'event_type_family' => $normalized['event_type_family'],
            'style_skin' => $normalized['style_skin'],
            'behavior_profile' => $normalized['behavior_profile'],
            'theme_key' => $normalized['theme_key'],
            'layout_key' => $normalized['layout_key'],
            'theme_tokens_json' => $normalized['theme_tokens'],
            'page_schema_json' => $normalized['page_schema'],
            'media_behavior_json' => $normalized['media_behavior'],
            'updated_by' => $user?->id,
        ]);

        $settings->save();

        return $settings->fresh([
            'currentDraftRevision',
            'currentPublishedRevision',
            'previewRevision',
        ]);
    }
}
