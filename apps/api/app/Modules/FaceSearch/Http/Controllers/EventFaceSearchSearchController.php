<?php

namespace App\Modules\FaceSearch\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\SearchFacesBySelfieAction;
use App\Modules\FaceSearch\Http\Requests\SearchEventFaceSearchRequest;
use App\Modules\FaceSearch\Http\Resources\EventFaceSearchRequestResource;
use App\Modules\FaceSearch\Http\Resources\FaceSearchMatchResource;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;

class EventFaceSearchSearchController extends BaseController
{
    public function store(
        SearchEventFaceSearchRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        SearchFacesBySelfieAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.moderate'), 403);

        $result = $action->execute(
            event: $event,
            selfie: $request->file('selfie'),
            requesterType: 'user',
            requesterUser: $request->user(),
            includePending: (bool) $request->boolean('include_pending', true),
        );

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties([
                'event_id' => $event->id,
                'face_search_request_id' => $result['request']->id,
                'result_count' => count($result['results']),
            ])
            ->log('Busca interna por selfie executada');

        return $this->success([
            'request' => new EventFaceSearchRequestResource($result['request']),
            'total_results' => count($result['results']),
            'results' => FaceSearchMatchResource::collection(collect($result['results'])),
        ]);
    }
}
