<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Http\Requests\ListAuditActivitiesRequest;
use App\Modules\Audit\Http\Requests\ListAuditFilterOptionsRequest;
use App\Modules\Audit\Http\Resources\AuditActivityResource;
use App\Modules\Audit\Queries\ListAuditActivitiesQuery;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class AuditController extends BaseController
{
    public function index(ListAuditActivitiesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = new ListAuditActivitiesQuery(
            viewer: $request->user(),
            organizationId: $validated['organization_id'] ?? null,
            actorId: $validated['actor_id'] ?? null,
            subjectType: $validated['subject_type'] ?? null,
            activityEvent: $validated['activity_event'] ?? null,
            search: $validated['search'] ?? null,
            batchUuid: $validated['batch_uuid'] ?? null,
            hasChanges: array_key_exists('has_changes', $validated) ? (bool) $validated['has_changes'] : null,
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
        );

        $logs = $query->query()->paginate($validated['per_page'] ?? 30);

        [$events, $organizations, $organizationMembers] = $this->buildResourceMaps(
            $logs->getCollection()
        );

        $data = $logs->getCollection()
            ->map(fn (Activity $activity) => (new AuditActivityResource(
                $activity,
                $events,
                $organizations,
                $organizationMembers,
            ))->toArray($request))
            ->values();

        $scopeOrganization = $query->scopedOrganizationId()
            ? $organizations->get($query->scopedOrganizationId())
            : null;

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
                'request_id' => 'req_' . Str::random(12),
                'scope' => [
                    'is_global' => $query->isGlobalScope(),
                    'organization_id' => $scopeOrganization?->id,
                    'organization_name' => $scopeOrganization
                        ? ($scopeOrganization->trade_name ?: $scopeOrganization->legal_name ?: $scopeOrganization->name)
                        : null,
                ],
            ],
        ]);
    }

    public function filters(ListAuditFilterOptionsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = new ListAuditActivitiesQuery(
            viewer: $request->user(),
            organizationId: $validated['organization_id'] ?? null,
        );

        $baseQuery = $query->query();

        $actorIds = (clone $baseQuery)
            ->reorder()
            ->whereNotNull('causer_id')
            ->select('causer_id')
            ->distinct()
            ->pluck('causer_id');

        $actors = User::query()
            ->whereIn('id', $actorIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $actor) => [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
            ])
            ->values();

        $subjectTypes = (clone $baseQuery)
            ->reorder()
            ->whereNotNull('subject_type')
            ->select('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(function (?string $type) {
                return match ($type) {
                    Event::class => ['key' => 'event', 'label' => 'Evento'],
                    Organization::class => ['key' => 'organization', 'label' => 'Organizacao'],
                    Client::class => ['key' => 'client', 'label' => 'Cliente'],
                    User::class => ['key' => 'user', 'label' => 'Usuario'],
                    Subscription::class => ['key' => 'subscription', 'label' => 'Assinatura'],
                    EventMedia::class => ['key' => 'media', 'label' => 'Midia'],
                    default => null,
                };
            })
            ->filter()
            ->values();

        $activityEvents = (clone $baseQuery)
            ->reorder()
            ->whereNotNull('event')
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->values();

        $scopeOrganization = null;
        if ($query->scopedOrganizationId() !== null) {
            $scopeOrganization = Organization::query()
                ->find($query->scopedOrganizationId(), ['id', 'trade_name', 'legal_name', 'slug']);
        }

        return $this->success([
            'actors' => $actors,
            'subject_types' => $subjectTypes,
            'activity_events' => $activityEvents,
            'scope' => [
                'is_global' => $query->isGlobalScope(),
                'organization_id' => $scopeOrganization?->id,
                'organization_name' => $scopeOrganization
                    ? ($scopeOrganization->trade_name ?: $scopeOrganization->legal_name ?: $scopeOrganization->name)
                    : null,
            ],
        ]);
    }

    private function buildResourceMaps(Collection $activities): array
    {
        $eventIds = $activities
            ->flatMap(function (Activity $activity) {
                $ids = [];

                if ($activity->subject_type === Event::class && $activity->subject_id !== null) {
                    $ids[] = (int) $activity->subject_id;
                }

                $eventId = $activity->properties['event_id'] ?? null;
                if (is_numeric($eventId)) {
                    $ids[] = (int) $eventId;
                }

                return $ids;
            })
            ->unique()
            ->values();

        $events = Event::query()
            ->whereIn('id', $eventIds)
            ->get(['id', 'title', 'slug', 'organization_id'])
            ->keyBy('id');

        $organizationIds = $activities
            ->flatMap(function (Activity $activity) use ($events) {
                $ids = [];

                if ($activity->subject_type === Organization::class && $activity->subject_id !== null) {
                    $ids[] = (int) $activity->subject_id;
                }

                if (in_array($activity->subject_type, [
                    Event::class,
                    Client::class,
                    Subscription::class,
                ], true) && $activity->subject && isset($activity->subject->organization_id)) {
                    $ids[] = (int) $activity->subject->organization_id;
                }

                $organizationId = $activity->properties['organization_id'] ?? null;
                if (is_numeric($organizationId)) {
                    $ids[] = (int) $organizationId;
                }

                $eventId = $activity->properties['event_id'] ?? null;
                if (is_numeric($eventId)) {
                    $relatedEvent = $events->get((int) $eventId);
                    if ($relatedEvent !== null) {
                        $ids[] = (int) $relatedEvent->organization_id;
                    }
                }

                return $ids;
            })
            ->unique()
            ->values();

        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->get(['id', 'trade_name', 'legal_name', 'slug'])
            ->keyBy('id');

        $userIds = $activities
            ->flatMap(function (Activity $activity) {
                $ids = [];

                if ($activity->subject_type === User::class && $activity->subject_id !== null) {
                    $ids[] = (int) $activity->subject_id;
                }

                if ($activity->causer_id !== null) {
                    $ids[] = (int) $activity->causer_id;
                }

                return $ids;
            })
            ->unique()
            ->values();

        $organizationMembers = OrganizationMember::query()
            ->active()
            ->whereIn('user_id', $userIds)
            ->pluck('organization_id', 'user_id');

        return [$events, $organizations, $organizationMembers];
    }
}
