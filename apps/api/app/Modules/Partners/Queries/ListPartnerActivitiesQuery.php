<?php

namespace App\Modules\Partners\Queries;

use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ListPartnerActivitiesQuery
{
    public function __construct(
        private readonly Organization $partner,
        private readonly ?string $activityEvent = null,
        private readonly ?string $search = null,
    ) {}

    public function query(): Builder
    {
        $partnerId = $this->partner->id;
        $eventIds = Event::query()->select('id')->where('organization_id', $partnerId);
        $clientIds = Client::query()->select('id')->where('organization_id', $partnerId);
        $userIds = OrganizationMember::query()->select('user_id')->where('organization_id', $partnerId);

        $query = Activity::query()
            ->with(['causer:id,name,email'])
            ->where(function (Builder $builder) use ($partnerId, $eventIds, $clientIds, $userIds) {
                $builder
                    ->where(function (Builder $subjectQuery) use ($partnerId) {
                        $subjectQuery
                            ->where('subject_type', Organization::class)
                            ->where('subject_id', $partnerId);
                    })
                    ->orWhere(function (Builder $subjectQuery) use ($eventIds) {
                        $subjectQuery
                            ->where('subject_type', Event::class)
                            ->whereIn('subject_id', $eventIds);
                    })
                    ->orWhere(function (Builder $subjectQuery) use ($clientIds) {
                        $subjectQuery
                            ->where('subject_type', Client::class)
                            ->whereIn('subject_id', $clientIds);
                    })
                    ->orWhere(function (Builder $subjectQuery) use ($userIds) {
                        $subjectQuery
                            ->where('subject_type', User::class)
                            ->whereIn('subject_id', $userIds);
                    })
                    ->orWhere('properties->partner_id', $partnerId)
                    ->orWhere('properties->organization_id', $partnerId)
                    ->orWhereIn('properties->event_id', $eventIds);
            });

        if ($this->activityEvent) {
            $query->where('event', $this->activityEvent);
        }

        if ($this->search && trim($this->search) !== '') {
            $needle = '%' . mb_strtolower(trim($this->search)) . '%';

            $query->where(function (Builder $builder) use ($needle) {
                $builder
                    ->whereRaw('LOWER(description) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(event, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(CAST(properties AS TEXT)) LIKE ?', [$needle])
                    ->orWhereHas('causer', function (Builder $causerQuery) use ($needle) {
                        $causerQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
                    });
            });
        }

        return $query->latest('created_at')->latest('id');
    }
}
