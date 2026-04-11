<?php

namespace App\Modules\EventTeam\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Actions\CreateEventTeamInvitationAction;
use App\Modules\EventTeam\Actions\DispatchEventTeamInvitationAction;
use App\Modules\EventTeam\Actions\ResendEventTeamInvitationAction;
use App\Modules\EventTeam\Actions\RevokeEventTeamInvitationAction;
use App\Modules\EventTeam\Http\Requests\ResendEventTeamInvitationRequest;
use App\Modules\EventTeam\Http\Requests\StoreEventTeamInvitationRequest;
use App\Modules\EventTeam\Http\Resources\EventTeamInvitationResource;
use App\Modules\EventTeam\Models\EventTeamInvitation;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        DispatchEventTeamInvitationAction $dispatchAction,
    ): JsonResponse {
        $this->authorize('manageTeam', $event);

        $payload = $request->validated();

        $invitation = $action->execute($event, $payload, $request->user());
        $invitation = $dispatchAction->execute($invitation, (bool) ($payload['send_via_whatsapp'] ?? false));

        return $this->success(EventTeamInvitationResource::make($invitation)->resolve(), 201);
    }

    public function resend(
        ResendEventTeamInvitationRequest $request,
        Event $event,
        EventTeamInvitation $invitation,
        ResendEventTeamInvitationAction $action,
    ): JsonResponse {
        $this->authorize('manageTeam', $event);
        $this->guardEventInvitation($event, $invitation);

        $invitation = $action->execute(
            $invitation,
            $request->user(),
            (bool) $request->validated('send_via_whatsapp'),
        );

        return $this->success(EventTeamInvitationResource::make($invitation)->resolve());
    }

    public function revoke(
        Event $event,
        EventTeamInvitation $invitation,
        RevokeEventTeamInvitationAction $action,
    ): JsonResponse {
        $this->authorize('manageTeam', $event);
        $this->guardEventInvitation($event, $invitation);

        $invitation = $action->execute($invitation, request()->user());

        return $this->success(EventTeamInvitationResource::make($invitation)->resolve());
    }

    private function guardEventInvitation(Event $event, EventTeamInvitation $invitation): void
    {
        if ((int) $invitation->event_id !== (int) $event->id) {
            throw new NotFoundHttpException();
        }
    }
}
