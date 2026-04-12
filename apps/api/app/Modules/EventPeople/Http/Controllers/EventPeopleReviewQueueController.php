<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\ConfirmEventPersonFaceAction;
use App\Modules\EventPeople\Actions\IgnoreEventPersonReviewItemAction;
use App\Modules\EventPeople\Actions\MergeEventPeopleAction;
use App\Modules\EventPeople\Actions\ProjectEventPeopleReviewQueueAction;
use App\Modules\EventPeople\Actions\SplitEventPersonFaceAction;
use App\Modules\EventPeople\Http\Requests\ConfirmEventPeopleReviewItemRequest;
use App\Modules\EventPeople\Http\Requests\ListEventPeopleReviewQueueRequest;
use App\Modules\EventPeople\Http\Requests\MergeEventPeopleRequest;
use App\Modules\EventPeople\Http\Requests\SplitEventPersonFaceRequest;
use App\Modules\EventPeople\Http\Resources\EventMediaFacePeopleResource;
use App\Modules\EventPeople\Http\Resources\EventPersonResource;
use App\Modules\EventPeople\Http\Resources\EventPersonReviewQueueResource;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\EventPeople\Queries\ListEventPeopleReviewQueueQuery;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPeopleReviewQueueController extends BaseController
{
    public function index(
        ListEventPeopleReviewQueueRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        ProjectEventPeopleReviewQueueAction $projectReviewQueue,
        ListEventPeopleReviewQueueQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $projectReviewQueue->executeForEvent($event, onlyMissing: false);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 36);

        return $this->paginated(EventPersonReviewQueueResource::collection($query->paginate($event, $filters, $perPage)));
    }

    public function confirm(
        ConfirmEventPeopleReviewItemRequest $request,
        Event $event,
        EventPersonReviewQueueItem $reviewItem,
        EventAccessService $eventAccess,
        ConfirmEventPersonFaceAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);
        abort_unless((int) $reviewItem->event_id === (int) $event->id, 404);
        abort_unless($reviewItem->event_media_face_id !== null, 422);

        $face = \App\Modules\FaceSearch\Models\EventMediaFace::query()
            ->with(['personAssignments.person', 'reviewQueueItems'])
            ->findOrFail($reviewItem->event_media_face_id);

        $result = $action->execute(
            $event,
            $face,
            $request->user(),
            $request->validated(),
            $reviewItem,
        );

        return $this->success([
            'person' => new EventPersonResource($result['person']),
            'face' => new EventMediaFacePeopleResource($result['face']),
            'review_item' => $result['review_item'] ? new EventPersonReviewQueueResource($result['review_item']) : null,
        ]);
    }

    public function ignore(
        Request $request,
        Event $event,
        EventPersonReviewQueueItem $reviewItem,
        EventAccessService $eventAccess,
        IgnoreEventPersonReviewItemAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);
        abort_unless((int) $reviewItem->event_id === (int) $event->id, 404);

        $updated = $action->execute($event, $reviewItem, $request->user(), 'ignored');

        return $this->success([
            'review_item' => new EventPersonReviewQueueResource($updated),
        ]);
    }

    public function reject(
        Request $request,
        Event $event,
        EventPersonReviewQueueItem $reviewItem,
        EventAccessService $eventAccess,
        IgnoreEventPersonReviewItemAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);
        abort_unless((int) $reviewItem->event_id === (int) $event->id, 404);

        $updated = $action->execute($event, $reviewItem, $request->user(), 'rejected');

        return $this->success([
            'review_item' => new EventPersonReviewQueueResource($updated),
        ]);
    }

    public function merge(
        MergeEventPeopleRequest $request,
        Event $event,
        EventPersonReviewQueueItem $reviewItem,
        EventAccessService $eventAccess,
        MergeEventPeopleAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);
        abort_unless((int) $reviewItem->event_id === (int) $event->id, 404);

        $payload = $request->validated();
        $sourcePerson = EventPerson::query()->findOrFail((int) $payload['source_person_id']);
        $targetPerson = EventPerson::query()->findOrFail((int) $payload['target_person_id']);

        $result = $action->execute($event, $sourcePerson, $targetPerson, $request->user(), $reviewItem);

        return $this->success([
            'source_person' => new EventPersonResource($result['source_person']),
            'target_person' => new EventPersonResource($result['target_person']),
            'review_item' => $result['review_item'] ? new EventPersonReviewQueueResource($result['review_item']) : null,
        ]);
    }

    public function split(
        SplitEventPersonFaceRequest $request,
        Event $event,
        EventPersonReviewQueueItem $reviewItem,
        EventAccessService $eventAccess,
        SplitEventPersonFaceAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);
        abort_unless((int) $reviewItem->event_id === (int) $event->id, 404);
        abort_unless($reviewItem->event_media_face_id !== null, 422);

        $face = \App\Modules\FaceSearch\Models\EventMediaFace::query()
            ->with(['personAssignments.person', 'reviewQueueItems'])
            ->findOrFail($reviewItem->event_media_face_id);

        $result = $action->execute($event, $face, $request->user(), $request->validated(), $reviewItem);

        return $this->success([
            'person' => isset($result['person']) ? new EventPersonResource($result['person']) : null,
            'face' => new EventMediaFacePeopleResource($result['face']),
            'review_item' => $result['review_item'] ? new EventPersonReviewQueueResource($result['review_item']) : null,
        ]);
    }
}
