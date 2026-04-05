<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Users\Models\User;

class MediaAuditLogger
{
    public function log(
        User $actor,
        EventMedia $eventMedia,
        string $event,
        string $description,
        array $old = [],
        array $attributes = [],
        array $context = [],
    ): void {
        $eventMedia->loadMissing(['event', 'inboundMessage']);

        $properties = [
            'organization_id' => $eventMedia->event?->organization_id,
            'event_id' => $eventMedia->event_id,
            'media_id' => $eventMedia->id,
            'media_type' => $eventMedia->media_type,
            'source_type' => $eventMedia->source_type,
            'source_label' => $eventMedia->source_label,
            'title' => $eventMedia->title,
            'caption' => $eventMedia->caption,
            'original_filename' => $eventMedia->original_filename,
            'sender_name' => $eventMedia->inboundMessage?->sender_name,
            ...$context,
        ];

        if ($old !== [] || $attributes !== []) {
            $properties['old'] = $old;
            $properties['attributes'] = $attributes;
        }

        activity()
            ->event($event)
            ->performedOn($eventMedia)
            ->causedBy($actor)
            ->withProperties($this->filterEmptyValues($properties))
            ->log($description);
    }

    private function filterEmptyValues(array $properties): array
    {
        return array_filter($properties, function (mixed $value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        });
    }
}
