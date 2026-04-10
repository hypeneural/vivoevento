<?php

namespace App\Modules\EventTeam\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Actions\CreateEventTeamInvitationAction;
use App\Modules\EventTeam\Http\Requests\StoreEventTeamInvitationRequest;
use App\Modules\EventTeam\Http\Resources\EventTeamInvitationResource;
use App\Modules\EventTeam\Models\EventTeamInvitation;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventTeamInvitationController extends BaseController
{
    public function index(Event $event): JsonResponse
    {
        $this->authorize('manageTeam', $event);

        $invitations = EventTeamInvitation::query()
            ->where('event_id', $event->id)
            ->orderByDesc('id')
            ->get();

        return $this->success(EventTeamInvitationResource::collection($invitations)->resolve());
    }

    public function store(
        StoreEventTeamInvitationRequest $request,
        Event $event,
        CreateEventTeamInvitationAction $action,
    ): JsonResponse {
        $this->authorize('manageTeam', $event);

        $invitation = $action->execute($event, $request->validated(), $request->user());

        return $this->success(EventTeamInvitationResource::make($invitation)->resolve(), 201);
    }
}
