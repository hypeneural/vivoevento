<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Actions\AddEventPersonGroupMemberAction;
use App\Modules\EventPeople\Actions\ApplyEventPersonGroupPresetAction;
use App\Modules\EventPeople\Actions\CreateEventPersonGroupAction;
use App\Modules\EventPeople\Actions\ProjectEventPersonGroupStatsAction;
use App\Modules\EventPeople\Actions\UpdateEventPersonGroupAction;
use App\Modules\EventPeople\Http\Requests\ListEventPersonGroupsRequest;
use App\Modules\EventPeople\Http\Requests\StoreEventPersonGroupMemberRequest;
use App\Modules\EventPeople\Http\Requests\StoreEventPersonGroupRequest;
use App\Modules\EventPeople\Http\Requests\UpdateEventPersonGroupRequest;
use App\Modules\EventPeople\Http\Resources\EventPersonGroupMembershipResource;
use App\Modules\EventPeople\Http\Resources\EventPersonGroupResource;
use App\Modules\EventPeople\Models\EventPersonGroup;
use App\Modules\EventPeople\Models\EventPersonGroupMembership;
use App\Modules\EventPeople\Queries\ListEventPersonGroupsQuery;
use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPersonGroupsController extends BaseController
{
    public function index(
        ListEventPersonGroupsRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        ListEventPersonGroupsQuery $query,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        return $this->success(EventPersonGroupResource::collection(
            $query->get($event, $request->validated())
        ));
    }

    public function store(
        StoreEventPersonGroupRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        CreateEventPersonGroupAction $action,
        ProjectEventPersonGroupStatsAction $projectStats,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);

        $group = $action->execute($event, $request->user(), $request->validated());
        $projectStats->executeForGroup($group);
        $group = $this->loadGroup($group);

        return $this->created(new EventPersonGroupResource($group));
    }

    public function update(
        UpdateEventPersonGroupRequest $request,
        Event $event,
        EventPersonGroup $group,
        EventAccessService $eventAccess,
        UpdateEventPersonGroupAction $action,
        ProjectEventPersonGroupStatsAction $projectStats,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $group->event_id === (int) $event->id, 404);

        $group = $action->execute($group, $request->user(), $request->validated());
        $projectStats->executeForGroup($group);
        $group = $this->loadGroup($group);

        return $this->success(new EventPersonGroupResource($group));
    }

    public function destroy(
        Request $request,
        Event $event,
        EventPersonGroup $group,
        EventAccessService $eventAccess,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $group->event_id === (int) $event->id, 404);

        $group->delete();

        return $this->noContent();
    }

    public function storeMember(
        StoreEventPersonGroupMemberRequest $request,
        Event $event,
        EventPersonGroup $group,
        EventAccessService $eventAccess,
        AddEventPersonGroupMemberAction $action,
        ProjectEventPersonGroupStatsAction $projectStats,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $group->event_id === (int) $event->id, 404);

        $membership = $action->execute($group, $request->user(), $request->validated())
            ->load('person');
        $projectStats->executeForGroup($group);

        return $this->created(new EventPersonGroupMembershipResource($membership));
    }

    public function destroyMember(
        Request $request,
        Event $event,
        EventPersonGroup $group,
        EventPersonGroupMembership $membership,
        EventAccessService $eventAccess,
        ProjectEventPersonGroupStatsAction $projectStats,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);
        abort_unless((int) $group->event_id === (int) $event->id, 404);
        abort_unless((int) $membership->event_id === (int) $event->id, 404);
        abort_unless((int) $membership->event_person_group_id === (int) $group->id, 404);

        $membership->delete();
        $projectStats->executeForGroup($group);

        return $this->noContent();
    }

    public function applyPreset(
        Request $request,
        Event $event,
        EventAccessService $eventAccess,
        ApplyEventPersonGroupPresetAction $action,
        ProjectEventPersonGroupStatsAction $projectStats,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'events.update'), 403);

        $groups = $action->execute($event, $request->user());
        foreach ($groups as $group) {
            $projectStats->executeForGroup($group);
        }

        return $this->success(EventPersonGroupResource::collection($this->loadGroups($groups)));
    }

    private function loadGroup(EventPersonGroup $group): EventPersonGroup
    {
        return $group->load([
            'groupStat',
            'groupMediaStat',
            'memberships.person.mediaStats',
            'memberships.person.primaryReferencePhoto',
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, EventPersonGroup>  $groups
     * @return \Illuminate\Database\Eloquent\Collection<int, EventPersonGroup>
     */
    private function loadGroups(\Illuminate\Database\Eloquent\Collection $groups): \Illuminate\Database\Eloquent\Collection
    {
        return $groups->load([
            'groupStat',
            'groupMediaStat',
            'memberships.person.mediaStats',
            'memberships.person.primaryReferencePhoto',
        ]);
    }
}
