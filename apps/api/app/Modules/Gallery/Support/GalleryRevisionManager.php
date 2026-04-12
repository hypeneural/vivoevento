<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Users\Models\User;

class GalleryRevisionManager
{
    /**
     * @param  array<string, mixed>  $changeSummary
     */
    public function createRevision(
        EventGallerySetting $settings,
        string $kind,
        ?User $user = null,
        array $changeSummary = [],
    ): EventGalleryRevision {
        $nextVersion = ((int) EventGalleryRevision::query()
            ->where('event_id', $settings->event_id)
            ->max('version_number')) + 1;

        return EventGalleryRevision::query()->create([
            'event_id' => $settings->event_id,
            'version_number' => $nextVersion,
            'kind' => $kind,
            'event_type_family' => $settings->event_type_family,
            'style_skin' => $settings->style_skin,
            'behavior_profile' => $settings->behavior_profile,
            'theme_key' => $settings->theme_key,
            'layout_key' => $settings->layout_key,
            'theme_tokens_json' => $settings->theme_tokens_json,
            'page_schema_json' => $settings->page_schema_json,
            'media_behavior_json' => $settings->media_behavior_json,
            'change_summary_json' => $changeSummary !== [] ? $changeSummary : null,
            'created_by' => $user?->id,
        ]);
    }

    public function applyRevisionToSettings(
        EventGallerySetting $settings,
        EventGalleryRevision $revision,
        ?User $user = null,
    ): EventGallerySetting {
        $settings->fill([
            'event_type_family' => $revision->event_type_family,
            'style_skin' => $revision->style_skin,
            'behavior_profile' => $revision->behavior_profile,
            'theme_key' => $revision->theme_key,
            'layout_key' => $revision->layout_key,
            'theme_tokens_json' => $revision->theme_tokens_json,
            'page_schema_json' => $revision->page_schema_json,
            'media_behavior_json' => $revision->media_behavior_json,
            'updated_by' => $user?->id,
        ]);

        $settings->save();

        return $settings->fresh();
    }
}
