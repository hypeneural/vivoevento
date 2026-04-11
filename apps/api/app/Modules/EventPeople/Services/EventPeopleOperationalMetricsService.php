<?php

namespace App\Modules\EventPeople\Services;

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonRepresentativeSyncStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;

class EventPeopleOperationalMetricsService
{
    /**
     * @return array<string, int>
     */
    public function snapshot(int $eventId): array
    {
        return [
            'people_active' => EventPerson::query()
                ->where('event_id', $eventId)
                ->where('status', EventPersonStatus::Active->value)
                ->count(),
            'people_draft' => EventPerson::query()
                ->where('event_id', $eventId)
                ->where('status', EventPersonStatus::Draft->value)
                ->count(),
            'assignments_confirmed' => EventPersonFaceAssignment::query()
                ->where('event_id', $eventId)
                ->where('status', EventPersonAssignmentStatus::Confirmed->value)
                ->count(),
            'review_queue_pending' => EventPersonReviewQueueItem::query()
                ->where('event_id', $eventId)
                ->where('status', EventPersonReviewQueueStatus::Pending->value)
                ->count(),
            'review_queue_conflict' => EventPersonReviewQueueItem::query()
                ->where('event_id', $eventId)
                ->where('status', EventPersonReviewQueueStatus::Conflict->value)
                ->count(),
            'aws_sync_pending' => EventPersonRepresentativeFace::query()
                ->where('event_id', $eventId)
                ->where('sync_status', EventPersonRepresentativeSyncStatus::Pending->value)
                ->count(),
            'aws_sync_failed' => EventPersonRepresentativeFace::query()
                ->where('event_id', $eventId)
                ->where('sync_status', EventPersonRepresentativeSyncStatus::Failed->value)
                ->count(),
        ];
    }
}
