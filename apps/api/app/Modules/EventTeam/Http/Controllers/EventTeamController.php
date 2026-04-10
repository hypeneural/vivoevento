<?php

namespace App\Modules\EventTeam\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Actions\UpsertEventTeamMemberAction;
use App\Modules\EventTeam\Http\Requests\StoreEventTeamMemberRequest;
use App\Modules\EventTeam\Http\Requests\UpdateEventTeamMemberRequest;
use App\Modules\EventTeam\Http\Resources\EventTeamMemberResource;
use App\Modules\EventTeam\Models\EventTeamMember;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventTeamController extends BaseController
{
    public function index(Event $event): JsonResponse
    {
        $this->authorize('manageTeam', $event);

        $members = EventTeamMember::query()
            ->where('event_id', $event->id)
            ->with('user:id,name,email,phone,avatar_path')
            ->get();

        return $this->success(EventTeamMemberResource::collection($members)->resolve());
    }

    public function store(
        StoreEventTeamMemberRequest $request,
        Event $event,
        UpsertEventTeamMemberAction $action,
    ): JsonResponse {
        $this->authorize('manageTeam', $event);

        $member = $action->execute($event, $request->validated(), $request->user());

        return $this->success(EventTeamMemberResource::make($member)->resolve(), 201);
    }

    public function update(
        UpdateEventTeamMemberRequest $request,
        Event $event,
        EventTeamMember $member,
        UpsertEventTeamMemberAction $action,
    ): JsonResponse {
        $this->authorize('manageTeam', $event);
        $this->guardEventMembership($event, $member);

        $member = $action->execute(
            $event,
            array_merge($request->validated(), ['user_id' => $member->user_id]),
            $request->user(),
            $member,
        );

        return $this->success(EventTeamMemberResource::make($member)->resolve());
    }

    public function destroy(Event $event, EventTeamMember $member): JsonResponse
    {
        $this->authorize('manageTeam', $event);
        $this->guardEventMembership($event, $member);

        $member->delete();

        return $this->success(null, 204);
    }

    private function guardEventMembership(Event $event, EventTeamMember $member): void
    {
        if ((int) $member->event_id !== (int) $event->id) {
            throw new NotFoundHttpException();
        }
    }
}
