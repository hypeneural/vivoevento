<?php

namespace App\Modules\EventPeople\Queries;

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Models\EventPerson;
use Illuminate\Database\Eloquent\Collection;

class ListEventPersonReferencePhotoCandidatesQuery
{
    /**
     * @return Collection<int, \App\Modules\EventPeople\Models\EventPersonFaceAssignment>
     */
    public function get(EventPerson $person, int $limit = 24): Collection
    {
        return $person->assignments()
            ->where('status', EventPersonAssignmentStatus::Confirmed->value)
            ->with(['face.media.variants'])
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
