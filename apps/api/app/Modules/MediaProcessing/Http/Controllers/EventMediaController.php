<?php

namespace App\Modules\MediaProcessing\Http\Controllers;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaDeleted;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventMediaController extends BaseController
{
    public function index(int $event): JsonResponse
    {
        $media = EventMedia::where('event_id', $event)
            ->with('variants')
            ->latest()
            ->paginate(30);

        return $this->success($media);
    }

    public function show(EventMedia $eventMedia): JsonResponse
    {
        return $this->success($eventMedia->load(['variants', 'processingRuns']));
    }

    public function approve(EventMedia $eventMedia): JsonResponse
    {
        $eventMedia->update(['moderation_status' => ModerationStatus::Approved]);

        $eventMedia = $eventMedia->fresh();

        if ($eventMedia->publication_status === PublicationStatus::Published) {
            event(MediaPublished::fromMedia($eventMedia));
        }

        return $this->success($eventMedia);
    }

    public function reject(EventMedia $eventMedia): JsonResponse
    {
        $eventMedia->update(['moderation_status' => ModerationStatus::Rejected]);

        $eventMedia = $eventMedia->fresh();

        event(MediaRejected::fromMedia($eventMedia));

        return $this->success($eventMedia);
    }

    public function destroy(EventMedia $eventMedia): JsonResponse
    {
        $eventMediaId = $eventMedia->id;

        $eventMedia->delete();

        event(new MediaDeleted($eventMediaId));

        return $this->noContent();
    }
}
