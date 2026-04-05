<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Events\ModerationMediaCreated;
use App\Modules\MediaProcessing\Events\ModerationMediaDeleted;
use App\Modules\MediaProcessing\Events\ModerationMediaUpdated;
use App\Modules\MediaProcessing\Http\Resources\EventMediaResource;
use App\Modules\MediaProcessing\Models\EventMedia;

class ModerationBroadcasterService
{
    public function broadcastCreated(EventMedia $media): void
    {
        $payload = $this->payloadFromMedia($media);

        if (! $payload) {
            return;
        }

        event(new ModerationMediaCreated(
            organizationId: $media->event->organization_id,
            payload: $payload,
        ));
    }

    public function broadcastUpdated(EventMedia $media): void
    {
        $payload = $this->payloadFromMedia($media);

        if (! $payload) {
            return;
        }

        event(new ModerationMediaUpdated(
            organizationId: $media->event->organization_id,
            payload: $payload,
        ));
    }

    public function broadcastDeleted(EventMedia $media): void
    {
        $media->loadMissing('event');

        if (! $media->event) {
            return;
        }

        event(new ModerationMediaDeleted(
            organizationId: $media->event->organization_id,
            payload: [
                'id' => $media->id,
                'event_id' => $media->event_id,
            ],
        ));
    }

    private function payloadFromMedia(EventMedia $media): ?array
    {
        $media->loadMissing(['event', 'variants', 'inboundMessage']);

        if (! $media->event) {
            return null;
        }

        return EventMediaResource::make($media)->resolve();
    }
}
