<?php

namespace App\Modules\EventPeople\Support;

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use App\Modules\EventPeople\Enums\EventPersonRepresentativeSyncStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonFaceAssignment;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use Carbon\CarbonInterface;
use DomainException;

class EventPeopleStateMachine
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function transitionReviewItem(
        EventPersonReviewQueueItem $item,
        EventPersonReviewQueueStatus $to,
        array $context = [],
    ): EventPersonReviewQueueItem {
        $from = $item->status instanceof EventPersonReviewQueueStatus
            ? $item->status
            : EventPersonReviewQueueStatus::from((string) $item->status);

        $this->guardTransition('review_item.status', $from->value, $to->value, [
            EventPersonReviewQueueStatus::Pending->value => [
                EventPersonReviewQueueStatus::Resolved->value,
                EventPersonReviewQueueStatus::Ignored->value,
                EventPersonReviewQueueStatus::Conflict->value,
            ],
            EventPersonReviewQueueStatus::Conflict->value => [
                EventPersonReviewQueueStatus::Resolved->value,
                EventPersonReviewQueueStatus::Ignored->value,
                EventPersonReviewQueueStatus::Pending->value,
            ],
            EventPersonReviewQueueStatus::Resolved->value => [
                EventPersonReviewQueueStatus::Pending->value,
                EventPersonReviewQueueStatus::Conflict->value,
                EventPersonReviewQueueStatus::Ignored->value,
            ],
            EventPersonReviewQueueStatus::Ignored->value => [
                EventPersonReviewQueueStatus::Pending->value,
                EventPersonReviewQueueStatus::Conflict->value,
                EventPersonReviewQueueStatus::Resolved->value,
            ],
        ]);

        $payload = array_merge($item->payload ?? [], [
            'state_transition' => $this->transitionPayload($from->value, $to->value, $context),
        ]);

        $item->forceFill([
            'status' => $to->value,
            'payload' => $payload,
            'resolved_at' => in_array($to, [EventPersonReviewQueueStatus::Resolved, EventPersonReviewQueueStatus::Ignored], true)
                ? ($context['resolved_at'] ?? now())
                : null,
            'resolved_by' => in_array($to, [EventPersonReviewQueueStatus::Resolved, EventPersonReviewQueueStatus::Ignored], true)
                ? ($context['resolved_by'] ?? $item->resolved_by)
                : null,
        ])->save();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function transitionAssignment(
        EventPersonFaceAssignment $assignment,
        EventPersonAssignmentStatus $to,
        array $context = [],
    ): EventPersonFaceAssignment {
        $from = $assignment->status instanceof EventPersonAssignmentStatus
            ? $assignment->status
            : EventPersonAssignmentStatus::from((string) $assignment->status);

        $this->guardTransition('assignment.status', $from->value, $to->value, [
            EventPersonAssignmentStatus::Suggested->value => [
                EventPersonAssignmentStatus::Confirmed->value,
                EventPersonAssignmentStatus::Rejected->value,
            ],
            EventPersonAssignmentStatus::Confirmed->value => [
                EventPersonAssignmentStatus::Rejected->value,
            ],
            EventPersonAssignmentStatus::Rejected->value => [
                EventPersonAssignmentStatus::Suggested->value,
                EventPersonAssignmentStatus::Confirmed->value,
            ],
        ]);

        $assignment->forceFill(array_filter([
            'status' => $to->value,
            'source' => $context['source'] ?? null,
            'reviewed_by' => $context['reviewed_by'] ?? null,
            'reviewed_at' => $context['reviewed_at'] ?? now(),
        ], static fn ($value): bool => $value !== null))->save();

        return $assignment;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function transitionReferencePhoto(
        EventPersonReferencePhoto $photo,
        EventPersonReferencePhotoStatus $to,
        array $context = [],
    ): EventPersonReferencePhoto {
        $from = $photo->status instanceof EventPersonReferencePhotoStatus
            ? $photo->status
            : EventPersonReferencePhotoStatus::from((string) $photo->status);

        $this->guardTransition('reference_photo.status', $from->value, $to->value, [
            EventPersonReferencePhotoStatus::Active->value => [
                EventPersonReferencePhotoStatus::Archived->value,
                EventPersonReferencePhotoStatus::Invalid->value,
            ],
            EventPersonReferencePhotoStatus::Archived->value => [
                EventPersonReferencePhotoStatus::Active->value,
            ],
            EventPersonReferencePhotoStatus::Invalid->value => [
                EventPersonReferencePhotoStatus::Archived->value,
            ],
        ]);

        $photo->forceFill([
            'status' => $to->value,
            'updated_by' => $context['updated_by'] ?? $photo->updated_by,
        ])->save();

        return $photo;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function transitionRepresentativeSync(
        EventPersonRepresentativeFace $representative,
        EventPersonRepresentativeSyncStatus $to,
        array $context = [],
    ): EventPersonRepresentativeFace {
        $from = $representative->sync_status instanceof EventPersonRepresentativeSyncStatus
            ? $representative->sync_status
            : EventPersonRepresentativeSyncStatus::from((string) $representative->sync_status);

        $this->guardTransition('remote_sync_status', $from->value, $to->value, [
            EventPersonRepresentativeSyncStatus::Pending->value => [
                EventPersonRepresentativeSyncStatus::Synced->value,
                EventPersonRepresentativeSyncStatus::Failed->value,
                EventPersonRepresentativeSyncStatus::Skipped->value,
            ],
            EventPersonRepresentativeSyncStatus::Failed->value => [
                EventPersonRepresentativeSyncStatus::Pending->value,
            ],
            EventPersonRepresentativeSyncStatus::Synced->value => [
                EventPersonRepresentativeSyncStatus::Pending->value,
            ],
            EventPersonRepresentativeSyncStatus::Skipped->value => [
                EventPersonRepresentativeSyncStatus::Pending->value,
            ],
        ]);

        $timestamp = $context['last_synced_at'] ?? ($to === EventPersonRepresentativeSyncStatus::Synced ? now() : null);

        $representative->forceFill([
            'sync_status' => $to->value,
            'last_synced_at' => $timestamp,
            'sync_payload' => $context['sync_payload'] ?? $representative->sync_payload,
        ])->save();

        return $representative;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function transitionPerson(EventPerson $person, EventPersonStatus $to, array $context = []): EventPerson
    {
        $from = $person->status instanceof EventPersonStatus
            ? $person->status
            : EventPersonStatus::from((string) $person->status);

        $this->guardTransition('person.status', $from->value, $to->value, [
            EventPersonStatus::Draft->value => [
                EventPersonStatus::Active->value,
                EventPersonStatus::Hidden->value,
            ],
            EventPersonStatus::Active->value => [
                EventPersonStatus::Draft->value,
                EventPersonStatus::Hidden->value,
            ],
            EventPersonStatus::Hidden->value => [
                EventPersonStatus::Active->value,
                EventPersonStatus::Draft->value,
            ],
        ]);

        $person->forceFill([
            'status' => $to->value,
            'updated_by' => $context['updated_by'] ?? $person->updated_by,
        ])->save();

        return $person;
    }

    /**
     * @param  array<string, array<int, string>>  $map
     */
    private function guardTransition(string $machine, string $from, string $to, array $map): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = $map[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new DomainException(sprintf('Transicao invalida em %s: %s -> %s', $machine, $from, $to));
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function transitionPayload(string $from, string $to, array $context): array
    {
        $occurredAt = $context['occurred_at'] ?? now();

        return [
            'from' => $from,
            'to' => $to,
            'reason' => $context['reason'] ?? null,
            'occurred_at' => $occurredAt instanceof CarbonInterface ? $occurredAt->toIso8601String() : (string) $occurredAt,
        ];
    }
}
