<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\FaceSearchRouter;
use App\Modules\MediaProcessing\Models\EventMedia;

class QueueEventFaceSearchReindexAction
{
    public function __construct(
        private readonly FaceSearchRouter $router,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Event $event): array
    {
        $settings = $event->faceSearchSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventFaceSearchSetting::defaultAttributes(),
        );

        if (! $settings->enabled) {
            return [
                'status' => 'skipped',
                'backend_key' => $this->router->backendForSettings($settings)->key(),
                'queued_media_count' => 0,
                'skipped_reason' => 'face_search_disabled',
            ];
        }

        $backend = $this->router->backendForSettings($settings);
        $backend->ensureEventBackend($event, $settings);

        $queued = 0;

        EventMedia::query()
            ->where('event_id', $event->id)
            ->where('media_type', 'image')
            ->orderBy('id')
            ->chunkById(200, function ($mediaItems) use (&$queued): void {
                foreach ($mediaItems as $media) {
                    IndexMediaFacesJob::dispatch($media->id);
                    $queued++;
                }
            });

        return [
            'status' => 'queued',
            'backend_key' => $backend->key(),
            'queued_media_count' => $queued,
        ];
    }
}
