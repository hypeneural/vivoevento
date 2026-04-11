<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\ProjectEventPeopleReviewQueueAction;
use App\Modules\EventPeople\Http\Resources\EventMediaFacePeopleResource;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventMediaPeopleController extends BaseController
{
    public function show(
        Request $request,
        Event $event,
        EventMedia $media,
        EventAccessService $eventAccess,
        ProjectEventPeopleReviewQueueAction $projectReviewQueue,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);
        abort_unless((int) $media->event_id === (int) $event->id, 404);

        $projectReviewQueue->executeForMedia($media, onlyMissing: false);

        $faces = $media->faces()
            ->with([
                'personAssignments.person',
                'reviewQueueItems' => fn ($query) => $query->orderByDesc('priority')->orderByDesc('last_signal_at'),
            ])
            ->orderBy('face_index')
            ->get();

        return $this->success(EventMediaFacePeopleResource::collection($faces));
    }
}
