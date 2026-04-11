<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Http\Requests\ListEventPeopleReviewQueueRequest;
use App\Modules\EventPeople\Http\Resources\EventPersonReviewQueueResource;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;

class EventPeopleReviewQueueController extends BaseController
{
    public function index(
        ListEventPeopleReviewQueueRequest $request,
        Event $event,
        EventAccessService $eventAccess,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 36);

        $query = EventPersonReviewQueueItem::query()
            ->where('event_id', $event->id)
            ->with(['person', 'face'])
            ->orderByDesc('priority')
            ->orderByDesc('last_signal_at')
            ->orderBy('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $this->paginated(EventPersonReviewQueueResource::collection($query->paginate($perPage)));
    }
}
