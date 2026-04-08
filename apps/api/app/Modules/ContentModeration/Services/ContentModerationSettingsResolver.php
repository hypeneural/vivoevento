<?php

namespace App\Modules\ContentModeration\Services;

use App\Modules\ContentModeration\Models\ContentModerationGlobalSetting;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;

class ContentModerationSettingsResolver
{
    public function resolveGlobal(): ContentModerationGlobalSetting
    {
        return ContentModerationGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            ContentModerationGlobalSetting::defaultAttributes(),
        );
    }

    public function resolveForEvent(Event $event): EventContentModerationSetting
    {
        /** @var EventContentModerationSetting|null $eventSettings */
        $eventSettings = $event->relationLoaded('contentModerationSettings')
            ? $event->contentModerationSettings
            : $event->contentModerationSettings()->first();

        if ($eventSettings) {
            $eventSettings->setAttribute('inherits_global', false);

            return $eventSettings;
        }

        $globalSettings = $this->resolveGlobal();
        $resolved = new EventContentModerationSetting(array_merge(
            $globalSettings->only([
                'provider_key',
                'mode',
                'threshold_version',
                'hard_block_thresholds_json',
                'review_thresholds_json',
                'fallback_mode',
                'analysis_scope',
                'normalized_text_context_mode',
                'enabled',
            ]),
            ['event_id' => $event->id],
        ));

        $resolved->setAttribute('inherits_global', true);

        return $resolved;
    }
}
