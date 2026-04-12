<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\SelectEventPersonGalleryReferencePhotoAction;
use App\Modules\EventPeople\Actions\SetEventPersonPrimaryPhotoAction;
use App\Modules\EventPeople\Actions\UploadEventPersonReferencePhotoAction;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use App\Modules\EventPeople\Http\Requests\ListEventPersonReferencePhotoCandidatesRequest;
use App\Modules\EventPeople\Http\Requests\StoreEventPersonGalleryReferencePhotoRequest;
use App\Modules\EventPeople\Http\Requests\UploadEventPersonReferencePhotoRequest;
use App\Modules\EventPeople\Http\Resources\EventPersonReferencePhotoCandidateResource;
use App\Modules\EventPeople\Http\Resources\EventPersonResource;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\EventPeople\Queries\ListEventPersonReferencePhotoCandidatesQuery;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPersonReferencePhotosController extends BaseController
{
    public function candidates(
        ListEventPersonReferencePhotoCandidatesRequest $request,
        Event $event,
        EventPerson $person,
        EventAccessService $eventAccess,
        ListEventPersonReferencePhotoCandidatesQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);
        abort_unless((int) $person->event_id === (int) $event->id, 404);

        $limit = (int) ($request->validated('limit') ?? 24);

        return $this->success(
            EventPersonReferencePhotoCandidateResource::collection($query->get($person, $limit))
        );
    }

    public function storeFromGallery(
        StoreEventPersonGalleryReferencePhotoRequest $request,
        Event $event,
        EventPerson $person,
        EventAccessService $eventAccess,
        SelectEventPersonGalleryReferencePhotoAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $person->event_id === (int) $event->id, 404);

        $face = EventMediaFace::query()->findOrFail((int) $request->validated('event_media_face_id'));
        $purpose = $request->validated('purpose');

        $action->execute(
            $person,
            $face,
            $request->user(),
            is_string($purpose)
                ? EventPersonReferencePhotoPurpose::from($purpose)
                : EventPersonReferencePhotoPurpose::Matching,
        );

        return $this->success(new EventPersonResource($this->loadPerson($person)));
    }

    public function upload(
        UploadEventPersonReferencePhotoRequest $request,
        Event $event,
        EventPerson $person,
        EventAccessService $eventAccess,
        UploadEventPersonReferencePhotoAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $person->event_id === (int) $event->id, 404);

        $purpose = $request->validated('purpose');

        $action->execute(
            $event,
            $person,
            $request->user(),
            $request->file('file'),
            is_string($purpose)
                ? EventPersonReferencePhotoPurpose::from($purpose)
                : EventPersonReferencePhotoPurpose::Matching,
        );

        return $this->created(new EventPersonResource($this->loadPerson($person)));
    }

    public function setPrimary(
        Request $request,
        Event $event,
        EventPerson $person,
        EventPersonReferencePhoto $referencePhoto,
        EventAccessService $eventAccess,
        SetEventPersonPrimaryPhotoAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $person->event_id === (int) $event->id, 404);
        abort_unless((int) $referencePhoto->event_person_id === (int) $person->id, 404);

        $action->execute($person, $referencePhoto, $request->user());

        return $this->success(new EventPersonResource($this->loadPerson($person)));
    }

    private function loadPerson(EventPerson $person): EventPerson
    {
        return $person->fresh([
            'mediaStats',
            'primaryReferencePhoto.face',
            'primaryReferencePhoto.uploadMedia',
            'referencePhotos.face',
            'referencePhotos.uploadMedia',
            'representativeFaces.face',
            'outgoingRelations.personA',
            'outgoingRelations.personB',
            'incomingRelations.personA',
            'incomingRelations.personB',
        ]);
    }
}
