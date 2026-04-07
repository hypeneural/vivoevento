<?php

namespace App\Modules\Partners\Http\Controllers;

use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Clients\Http\Resources\ClientResource;
use App\Modules\Clients\Queries\ListClientsQuery;
use App\Modules\Events\Enums\EventCommercialMode;
use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Http\Requests\ListEventsRequest;
use App\Modules\Events\Http\Resources\EventListResource;
use App\Modules\Events\Queries\ListEventsQuery;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Partners\Actions\CreatePartnerAction;
use App\Modules\Partners\Actions\CreatePartnerGrantAction;
use App\Modules\Partners\Actions\DeletePartnerAction;
use App\Modules\Partners\Actions\EnsurePartnerStatsProjectionAction;
use App\Modules\Partners\Actions\InvitePartnerStaffAction;
use App\Modules\Partners\Actions\RebuildPartnerStatsAction;
use App\Modules\Partners\Actions\SuspendPartnerAction;
use App\Modules\Partners\Actions\UpdatePartnerAction;
use App\Modules\Partners\Http\Requests\ListPartnerActivityRequest;
use App\Modules\Partners\Http\Requests\ListPartnerClientsRequest;
use App\Modules\Partners\Http\Requests\ListPartnerGrantsRequest;
use App\Modules\Partners\Http\Requests\ListPartnersRequest;
use App\Modules\Partners\Http\Requests\ListPartnerStaffRequest;
use App\Modules\Partners\Http\Requests\StorePartnerGrantRequest;
use App\Modules\Partners\Http\Requests\StorePartnerRequest;
use App\Modules\Partners\Http\Requests\StorePartnerStaffRequest;
use App\Modules\Partners\Http\Requests\SuspendPartnerRequest;
use App\Modules\Partners\Http\Requests\UpdatePartnerRequest;
use App\Modules\Partners\Http\Resources\PartnerActivityResource;
use App\Modules\Partners\Http\Resources\PartnerDetailResource;
use App\Modules\Partners\Http\Resources\PartnerGrantResource;
use App\Modules\Partners\Http\Resources\PartnerResource;
use App\Modules\Partners\Http\Resources\PartnerStaffMemberResource;
use App\Modules\Partners\Queries\ListPartnerActivitiesQuery;
use App\Modules\Partners\Queries\ListPartnersQuery;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PartnerController extends BaseController
{
    public function __construct(
        private readonly RebuildPartnerStatsAction $rebuildPartnerStats,
    ) {}

    public function index(
        ListPartnersRequest $request,
        EnsurePartnerStatsProjectionAction $ensurePartnerStatsProjection,
    ): JsonResponse {
        Gate::authorize('viewAnyPartners');

        $ensurePartnerStatsProjection->execute();

        $validated = $request->validated();

        $partners = (new ListPartnersQuery(
            search: $validated['search'] ?? null,
            segment: $validated['segment'] ?? null,
            status: $validated['status'] ?? null,
            planCode: $validated['plan_code'] ?? null,
            subscriptionStatus: $validated['subscription_status'] ?? null,
            hasActiveEvents: $request->boolean('has_active_events', false)
                ? true
                : ($request->has('has_active_events') ? false : null),
            hasClients: $request->boolean('has_clients', false)
                ? true
                : ($request->has('has_clients') ? false : null),
            hasActiveBonusGrants: $request->boolean('has_active_bonus_grants', false)
                ? true
                : ($request->has('has_active_bonus_grants') ? false : null),
            sortBy: $validated['sort_by'] ?? 'created_at',
            sortDirection: $validated['sort_direction'] ?? 'desc',
        ))
            ->query()
            ->paginate($validated['per_page'] ?? 20);

        return $this->paginated(PartnerResource::collection($partners));
    }

    public function store(StorePartnerRequest $request, CreatePartnerAction $action): JsonResponse
    {
        Gate::authorize('createPartner');

        $partner = $action->execute($request->validated(), $request->user());

        return $this->created(new PartnerResource($partner));
    }

    public function show(Organization $partner): JsonResponse
    {
        Gate::authorize('viewPartner', $partner);

        $this->rebuildPartnerStats->execute($partner->fresh(['subscriptions.plan']));

        $partner = $partner->fresh([
            'partnerProfile',
            'partnerStats',
            'subscription.plan',
            'members.user',
        ]);

        $activity = (new ListPartnerActivitiesQuery($partner))
            ->query()
            ->limit(10)
            ->get();

        return $this->success(new PartnerDetailResource(
            $partner,
            eventsSummary: $this->buildEventsSummary($partner),
            clientsSummary: [
                'total' => $partner->clients()->count(),
            ],
            staffSummary: [
                'total' => $partner->members()->where('status', 'active')->count(),
                'owners' => $partner->members()->where('status', 'active')->where('is_owner', true)->count(),
            ],
            grantsSummary: [
                'active_bonus' => EventAccessGrant::query()
                    ->activeAt()
                    ->where('organization_id', $partner->id)
                    ->where('source_type', EventAccessGrantSourceType::Bonus->value)
                    ->count(),
                'active_manual_override' => EventAccessGrant::query()
                    ->activeAt()
                    ->where('organization_id', $partner->id)
                    ->where('source_type', EventAccessGrantSourceType::ManualOverride->value)
                    ->count(),
            ],
            latestActivity: $activity,
        ));
    }

    public function update(UpdatePartnerRequest $request, Organization $partner, UpdatePartnerAction $action): JsonResponse
    {
        Gate::authorize('updatePartner', $partner);

        $partner = $action->execute($partner, $request->validated(), $request->user());

        return $this->success(new PartnerResource($partner));
    }

    public function suspend(SuspendPartnerRequest $request, Organization $partner, SuspendPartnerAction $action): JsonResponse
    {
        Gate::authorize('suspendPartner', $partner);

        $partner = $action->execute($partner, $request->validated(), $request->user());

        return $this->success(new PartnerResource($partner));
    }

    public function destroy(Organization $partner, DeletePartnerAction $action): JsonResponse
    {
        Gate::authorize('deletePartner', $partner);

        if (! $action->execute($partner, request()->user())) {
            return $this->error('Parceiro possui historico operacional. Use suspensao.', 409);
        }

        return $this->noContent();
    }

    public function events(ListEventsRequest $request, Organization $partner): JsonResponse
    {
        Gate::authorize('viewPartner', $partner);

        $validated = $request->validated();

        $events = (new ListEventsQuery(
            organizationId: $partner->id,
            clientId: $validated['client_id'] ?? null,
            status: $validated['status'] ?? null,
            eventType: $validated['event_type'] ?? null,
            commercialMode: $validated['commercial_mode'] ?? null,
            module: $validated['module'] ?? null,
            search: $validated['search'] ?? null,
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
            sortBy: $validated['sort_by'] ?? 'starts_at',
            sortDirection: $validated['sort_direction'] ?? 'desc',
        ))
            ->query()
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(EventListResource::collection($events));
    }

    public function clients(ListPartnerClientsRequest $request, Organization $partner): JsonResponse
    {
        Gate::authorize('viewPartner', $partner);

        $validated = $request->validated();

        $clients = (new ListClientsQuery(
            organizationId: $partner->id,
            search: $validated['search'] ?? null,
            type: $validated['type'] ?? null,
            planCode: $validated['plan_code'] ?? null,
            hasEvents: $this->nullableBoolean($request, 'has_events'),
            sortBy: $validated['sort_by'] ?? 'created_at',
            sortDirection: $validated['sort_direction'] ?? 'desc',
        ))
            ->query()
            ->paginate($validated['per_page'] ?? 15);

        return $this->paginated(ClientResource::collection($clients));
    }

    public function staff(ListPartnerStaffRequest $request, Organization $partner): JsonResponse
    {
        Gate::authorize('viewPartner', $partner);

        $validated = $request->validated();
        $query = OrganizationMember::query()
            ->with('user:id,name,email,phone')
            ->where('organization_id', $partner->id);

        if ($validated['search'] ?? null) {
            $needle = $validated['search'];
            $query->whereHas('user', function ($builder) use ($needle) {
                $builder
                    ->where('name', 'like', "%{$needle}%")
                    ->orWhere('email', 'like', "%{$needle}%");
            });
        }

        if ($validated['role_key'] ?? null) {
            $query->where('role_key', $validated['role_key']);
        }

        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        }

        $members = $query->orderByDesc('is_owner')->orderBy('id')->paginate($validated['per_page'] ?? 25);

        return $this->paginated(PartnerStaffMemberResource::collection($members));
    }

    public function storeStaff(
        StorePartnerStaffRequest $request,
        Organization $partner,
        InvitePartnerStaffAction $action,
    ): JsonResponse {
        Gate::authorize('managePartnerStaff', $partner);

        $member = $action->execute($partner, $request->validated(), $request->user());

        return $this->created(new PartnerStaffMemberResource($member));
    }

    public function grants(ListPartnerGrantsRequest $request, Organization $partner): JsonResponse
    {
        Gate::authorize('viewPartner', $partner);

        $validated = $request->validated();
        $query = EventAccessGrant::query()
            ->with(['event:id,title,slug', 'grantedBy:id,name,email'])
            ->where('organization_id', $partner->id);

        if ($validated['event_id'] ?? null) {
            $query->where('event_id', $validated['event_id']);
        }

        if ($validated['source_type'] ?? null) {
            $query->where('source_type', $validated['source_type']);
        }

        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        }

        $grants = $query->latest('id')->paginate($validated['per_page'] ?? 25);

        return $this->paginated(PartnerGrantResource::collection($grants));
    }

    public function storeGrant(
        StorePartnerGrantRequest $request,
        Organization $partner,
        CreatePartnerGrantAction $action,
    ): JsonResponse {
        Gate::authorize('managePartnerGrants', $partner);

        $grant = $action->execute($partner, $request->validated(), $request->user());

        return $this->created(new PartnerGrantResource($grant));
    }

    public function activity(ListPartnerActivityRequest $request, Organization $partner): JsonResponse
    {
        Gate::authorize('viewPartner', $partner);

        $validated = $request->validated();
        $activity = (new ListPartnerActivitiesQuery(
            $partner,
            $validated['activity_event'] ?? null,
            $validated['search'] ?? null,
        ))
            ->query()
            ->paginate($validated['per_page'] ?? 25);

        return $this->paginated(PartnerActivityResource::collection($activity));
    }

    private function buildEventsSummary(Organization $partner): array
    {
        return [
            'total' => $partner->events()->count(),
            'active' => $partner->events()->where('status', EventStatus::Active->value)->count(),
            'draft' => $partner->events()->where('status', EventStatus::Draft->value)->count(),
            'bonus' => $partner->events()->where('commercial_mode', EventCommercialMode::Bonus->value)->count(),
            'manual_override' => $partner->events()->where('commercial_mode', EventCommercialMode::ManualOverride->value)->count(),
            'single_purchase' => $partner->events()->where('commercial_mode', EventCommercialMode::SinglePurchase->value)->count(),
            'subscription_covered' => $partner->events()->where('commercial_mode', EventCommercialMode::SubscriptionCovered->value)->count(),
        ];
    }

    private function nullableBoolean(Request $request, string $key): ?bool
    {
        if (! $request->has($key)) {
            return null;
        }

        return $request->boolean($key);
    }
}
