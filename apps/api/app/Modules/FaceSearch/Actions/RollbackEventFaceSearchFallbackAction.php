<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use Illuminate\Support\Arr;

class RollbackEventFaceSearchFallbackAction
{
    public function __construct(
        private readonly UpsertEventFaceSearchSettingsAction $upsertSettings,
    ) {}

    /**
     * @param array<string, mixed> $settingsSnapshot
     * @return array<string, mixed>
     */
    public function execute(Event $event, array $settingsSnapshot): array
    {
        $restored = $this->upsertSettings->execute(
            $event,
            Arr::only($settingsSnapshot, EventFaceSearchSetting::configurableAttributeKeys()),
        );

        return [
            'status' => 'rolled_back',
            'event_id' => $event->id,
            'event_title' => $event->title,
            'current_settings' => Arr::only(
                array_replace(EventFaceSearchSetting::defaultAttributes(), $restored->toArray()),
                EventFaceSearchSetting::configurableAttributeKeys(),
            ),
        ];
    }
}
