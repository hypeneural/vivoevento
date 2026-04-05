<?php

namespace App\Modules\Audit\Queries;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use App\Shared\Contracts\QueryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ListAuditActivitiesQuery implements QueryInterface
{
    private const SUBJECT_TYPE_MAP = [
        'event' => Event::class,
        'organization' => Organization::class,
        'client' => Client::class,
        'user' => User::class,
        'subscription' => Subscription::class,
        'media' => EventMedia::class,
    ];

    private readonly bool $globalScope;

    private readonly ?int $scopeOrganizationId;

    public function __construct(
        private readonly User $viewer,
        ?int $organizationId = null,
        private readonly ?int $actorId = null,
        private readonly ?string $subjectType = null,
        private readonly ?string $activityEvent = null,
        private readonly ?string $search = null,
        private readonly ?string $batchUuid = null,
        private readonly ?bool $hasChanges = null,
        private readonly ?string $dateFrom = null,
        private readonly ?string $dateTo = null,
    ) {
        $this->globalScope = $this->viewer->hasAnyRole(['super-admin', 'platform-admin']);

        $currentOrganizationId = $this->viewer->currentOrganization()?->id;

        if (! $this->globalScope && $organizationId !== null && $organizationId !== $currentOrganizationId) {
            throw new AuthorizationException('Voce nao pode consultar logs de outra organizacao.');
        }

        $this->scopeOrganizationId = $this->globalScope
            ? $organizationId
            : $currentOrganizationId;
    }

    public function query(): Builder
    {
        $query = Activity::query()
            ->with([
                'causer:id,name,email',
                'subject',
            ]);

        $this->applyVisibilityScope($query);
        $this->applyFilters($query);

        return $query->latest('created_at')->latest('id');
    }

    public function isGlobalScope(): bool
    {
        return $this->globalScope && $this->scopeOrganizationId === null;
    }

    public function scopedOrganizationId(): ?int
    {
        return $this->scopeOrganizationId;
    }

    private function applyVisibilityScope(Builder $query): void
    {
        if ($this->globalScope && $this->scopeOrganizationId === null) {
            return;
        }

        if ($this->scopeOrganizationId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $organizationId = $this->scopeOrganizationId;
        $organizationUserIds = OrganizationMember::query()
            ->select('user_id')
            ->where('organization_id', $organizationId);

        $organizationEventIds = Event::query()
            ->select('id')
            ->where('organization_id', $organizationId);

        $organizationClientIds = Client::query()
            ->select('id')
            ->where('organization_id', $organizationId);

        $organizationSubscriptionIds = Subscription::query()
            ->select('id')
            ->where('organization_id', $organizationId);

        $query->where(function (Builder $scopedQuery) use (
            $organizationId,
            $organizationUserIds,
            $organizationEventIds,
            $organizationClientIds,
            $organizationSubscriptionIds,
        ) {
            $scopedQuery
                ->where(function (Builder $subjectQuery) use ($organizationId) {
                    $subjectQuery
                        ->where('subject_type', Organization::class)
                        ->where('subject_id', $organizationId);
                })
                ->orWhere(function (Builder $subjectQuery) use ($organizationEventIds) {
                    $subjectQuery
                        ->where('subject_type', Event::class)
                        ->whereIn('subject_id', $organizationEventIds);
                })
                ->orWhere(function (Builder $subjectQuery) use ($organizationClientIds) {
                    $subjectQuery
                        ->where('subject_type', Client::class)
                        ->whereIn('subject_id', $organizationClientIds);
                })
                ->orWhere(function (Builder $subjectQuery) use ($organizationUserIds) {
                    $subjectQuery
                        ->where('subject_type', User::class)
                        ->whereIn('subject_id', $organizationUserIds);
                })
                ->orWhere(function (Builder $subjectQuery) use ($organizationSubscriptionIds) {
                    $subjectQuery
                        ->where('subject_type', Subscription::class)
                        ->whereIn('subject_id', $organizationSubscriptionIds);
                })
                ->orWhere('properties->organization_id', $organizationId)
                ->orWhereIn('properties->event_id', $organizationEventIds);
        });
    }

    private function applyFilters(Builder $query): void
    {
        if ($this->actorId !== null) {
            $query
                ->where('causer_type', User::class)
                ->where('causer_id', $this->actorId);
        }

        if ($this->subjectType !== null && isset(self::SUBJECT_TYPE_MAP[$this->subjectType])) {
            $query->where('subject_type', self::SUBJECT_TYPE_MAP[$this->subjectType]);
        }

        if ($this->activityEvent !== null && $this->activityEvent !== '') {
            $query->where('event', $this->activityEvent);
        }

        if ($this->batchUuid !== null && $this->batchUuid !== '') {
            $query->where('batch_uuid', $this->batchUuid);
        }

        if ($this->hasChanges === true) {
            $query->where(function (Builder $changesQuery) {
                $changesQuery
                    ->whereNotNull('properties->old')
                    ->orWhereNotNull('properties->attributes');
            });
        }

        if ($this->dateFrom !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($this->dateFrom)->startOfDay());
        }

        if ($this->dateTo !== null) {
            $query->where('created_at', '<=', CarbonImmutable::parse($this->dateTo)->endOfDay());
        }

        if ($this->search !== null && trim($this->search) !== '') {
            $normalizedSearch = '%' . mb_strtolower(trim($this->search)) . '%';

            $query->where(function (Builder $searchQuery) use ($normalizedSearch) {
                $searchQuery
                    ->whereRaw('LOWER(description) LIKE ?', [$normalizedSearch])
                    ->orWhereRaw('LOWER(COALESCE(event, \'\')) LIKE ?', [$normalizedSearch])
                    ->orWhereRaw('LOWER(CAST(properties AS TEXT)) LIKE ?', [$normalizedSearch])
                    ->orWhereHas('causer', function (Builder $causerQuery) use ($normalizedSearch) {
                        $causerQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$normalizedSearch])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$normalizedSearch]);
                    });
            });
        }
    }
}
