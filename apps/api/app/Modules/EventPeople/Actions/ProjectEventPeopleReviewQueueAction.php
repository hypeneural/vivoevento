<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueType;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;

class ProjectEventPeopleReviewQueueAction
{
    public function executeForEvent(Event $event, bool $onlyMissing = true): void
    {
        EventMediaFace::query()
            ->where('event_id', $event->id)
            ->orderBy('id')
            ->chunk(200, function ($faces) use ($onlyMissing): void {
                $faces->loadMissing('personAssignments.person');

                foreach ($faces as $face) {
                    $this->executeForFace($face, onlyMissing: $onlyMissing);
                }
            });
    }

    public function executeForMedia(EventMedia $media, bool $onlyMissing = true): void
    {
        $faces = $media->faces()
            ->with('personAssignments.person')
            ->orderBy('face_index')
            ->get();

        foreach ($faces as $face) {
            $this->executeForFace($face, onlyMissing: $onlyMissing);
        }
    }

    public function executeForFace(
        EventMediaFace $face,
        bool $reopenIgnored = false,
        bool $onlyMissing = false,
    ): ?EventPersonReviewQueueItem {
        $face->loadMissing('personAssignments.person');

        $queueItem = EventPersonReviewQueueItem::query()
            ->where('event_id', $face->event_id)
            ->where('queue_key', $this->unknownQueueKey($face))
            ->first();

        if ($onlyMissing && $queueItem) {
            return $queueItem;
        }

        $confirmedAssignment = $this->confirmedAssignment($face);

        if ($confirmedAssignment) {
            $primaryItem = $this->upsertResolvedItem($face, $confirmedAssignment->event_person_id);

            return $this->syncConflictItem($face, $confirmedAssignment) ?? $primaryItem;
        }

        $primaryItem = null;

        if (! $face->searchable) {
            if ($queueItem) {
                if (($queueItem->status?->value ?? $queueItem->status) === EventPersonReviewQueueStatus::Ignored->value && ! $reopenIgnored) {
                    $primaryItem = $queueItem;
                } else {
                    $primaryItem = $this->upsertIgnoredItem($face, $queueItem);
                }
            }
        } elseif (
            $queueItem
            && ($queueItem->status?->value ?? $queueItem->status) === EventPersonReviewQueueStatus::Ignored->value
            && ! $reopenIgnored
        ) {
            $primaryItem = $queueItem;
        } else {
            $primaryItem = $this->upsertPendingItem($face, $queueItem);
        }

        return $this->syncConflictItem($face, null) ?? $primaryItem;
    }

    private function confirmedAssignment(EventMediaFace $face): ?\App\Modules\EventPeople\Models\EventPersonFaceAssignment
    {
        return $face->personAssignments
            ->first(fn ($assignment): bool => ($assignment->status?->value ?? $assignment->status) === EventPersonAssignmentStatus::Confirmed->value);
    }

    private function upsertPendingItem(EventMediaFace $face, ?EventPersonReviewQueueItem $existing): EventPersonReviewQueueItem
    {
        $item = $existing ?? new EventPersonReviewQueueItem();
        $payload = $this->pendingPayload($face);
        $isAlreadyPending = $existing
            && ($existing->status?->value ?? $existing->status) === EventPersonReviewQueueStatus::Pending->value
            && $existing->event_person_id === null
            && $existing->event_media_face_id === $face->id
            && $existing->priority === $this->priorityForFace($face)
            && ($existing->payload ?? []) === $payload;

        if ($isAlreadyPending) {
            return $existing;
        }

        $item->fill([
            'event_id' => $face->event_id,
            'queue_key' => $this->unknownQueueKey($face),
            'type' => EventPersonReviewQueueType::UnknownPerson->value,
            'status' => EventPersonReviewQueueStatus::Pending->value,
            'priority' => $this->priorityForFace($face),
            'event_person_id' => null,
            'event_media_face_id' => $face->id,
            'payload' => $payload,
            'last_signal_at' => now(),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        $item->save();

        return $item;
    }

    private function upsertResolvedItem(EventMediaFace $face, int $eventPersonId): EventPersonReviewQueueItem
    {
        $item = EventPersonReviewQueueItem::query()->firstOrNew([
            'event_id' => $face->event_id,
            'queue_key' => $this->unknownQueueKey($face),
        ]);
        $payload = $this->resolvedPayload($face, $eventPersonId);
        $isAlreadyResolved = $item->exists
            && ($item->status?->value ?? $item->status) === EventPersonReviewQueueStatus::Resolved->value
            && (int) $item->event_person_id === $eventPersonId
            && ($item->payload ?? []) === $payload;

        if ($isAlreadyResolved) {
            return $item;
        }

        $item->fill([
            'type' => EventPersonReviewQueueType::UnknownPerson->value,
            'status' => EventPersonReviewQueueStatus::Resolved->value,
            'priority' => $this->priorityForFace($face),
            'event_person_id' => $eventPersonId,
            'event_media_face_id' => $face->id,
            'payload' => $payload,
            'last_signal_at' => now(),
            'resolved_at' => now(),
        ]);

        $item->save();

        return $item;
    }

    private function upsertIgnoredItem(EventMediaFace $face, EventPersonReviewQueueItem $item): EventPersonReviewQueueItem
    {
        $payload = array_merge($item->payload ?? [], [
            'question' => 'Quem e esta pessoa?',
            'resolution' => 'ignored',
            'ignore_reason' => $face->quality_rejection_reason ?: 'face_not_searchable',
        ]);

        $isAlreadyIgnored = ($item->status?->value ?? $item->status) === EventPersonReviewQueueStatus::Ignored->value
            && ($item->payload ?? []) === $payload;

        if ($isAlreadyIgnored) {
            return $item;
        }

        $item->fill([
            'type' => EventPersonReviewQueueType::UnknownPerson->value,
            'status' => EventPersonReviewQueueStatus::Ignored->value,
            'priority' => $this->priorityForFace($face),
            'event_person_id' => null,
            'event_media_face_id' => $face->id,
            'payload' => $payload,
            'last_signal_at' => now(),
            'resolved_at' => $item->resolved_at ?? now(),
        ]);

        $item->save();

        return $item;
    }

    private function syncConflictItem(
        EventMediaFace $face,
        ?\App\Modules\EventPeople\Models\EventPersonFaceAssignment $confirmedAssignment,
    ): ?EventPersonReviewQueueItem {
        $candidateAssignments = $face->personAssignments
            ->filter(fn ($assignment): bool => $assignment->person !== null)
            ->unique('event_person_id')
            ->values();

        $candidatePeople = $candidateAssignments
            ->map(fn ($assignment): array => [
                'id' => $assignment->person->id,
                'display_name' => $assignment->person->display_name,
                'type' => $assignment->person->type?->value ?? $assignment->person->type,
                'side' => $assignment->person->side?->value ?? $assignment->person->side,
                'status' => $assignment->person->status?->value ?? $assignment->person->status,
                'assignment_status' => $assignment->status?->value ?? $assignment->status,
                'source' => $assignment->source?->value ?? $assignment->source,
            ])
            ->sortByDesc(fn (array $person): int => $confirmedAssignment && (int) $person['id'] === (int) $confirmedAssignment->event_person_id ? 1 : 0)
            ->values()
            ->all();

        $item = EventPersonReviewQueueItem::query()->firstOrNew([
            'event_id' => $face->event_id,
            'queue_key' => $this->conflictQueueKey($face),
        ]);

        if ($confirmedAssignment && count($candidatePeople) >= 2) {
            $payload = $this->conflictPayload($face, $candidatePeople, $confirmedAssignment->event_person_id);
            $priority = $this->priorityForFace($face) + 75;
            $isAlreadyConflict = $item->exists
                && ($item->status?->value ?? $item->status) === EventPersonReviewQueueStatus::Conflict->value
                && $item->priority === $priority
                && (int) $item->event_person_id === (int) $confirmedAssignment->event_person_id
                && ($item->payload ?? []) === $payload;

            if ($isAlreadyConflict) {
                return $item;
            }

            $item->fill([
                'type' => EventPersonReviewQueueType::IdentityConflict->value,
                'status' => EventPersonReviewQueueStatus::Conflict->value,
                'priority' => $priority,
                'event_person_id' => $confirmedAssignment->event_person_id,
                'event_media_face_id' => $face->id,
                'payload' => $payload,
                'last_signal_at' => now(),
                'resolved_at' => null,
                'resolved_by' => null,
            ]);

            $item->save();

            return $item;
        }

        if (! $item->exists) {
            return null;
        }

        $payload = array_filter([
            'question' => 'Essa identidade precisa ser revisada?',
            'resolution' => 'stable_identity',
            'event_media_id' => $face->event_media_id,
            'face_index' => $face->face_index,
            'current_person_id' => $confirmedAssignment?->event_person_id,
            'candidate_people' => $candidatePeople,
        ], static fn ($value): bool => $value !== null);

        $isAlreadyResolved = ($item->status?->value ?? $item->status) === EventPersonReviewQueueStatus::Resolved->value
            && (int) ($item->event_person_id ?? 0) === (int) ($confirmedAssignment?->event_person_id ?? 0)
            && ($item->payload ?? []) === $payload;

        if ($isAlreadyResolved) {
            return null;
        }

        $item->fill([
            'type' => EventPersonReviewQueueType::IdentityConflict->value,
            'status' => EventPersonReviewQueueStatus::Resolved->value,
            'priority' => 0,
            'event_person_id' => $confirmedAssignment?->event_person_id,
            'event_media_face_id' => $face->id,
            'payload' => $payload,
            'last_signal_at' => now(),
            'resolved_at' => now(),
        ]);

        $item->save();

        return null;
    }

    private function priorityForFace(EventMediaFace $face): int
    {
        $tierBase = match ($face->quality_tier) {
            'search_priority' => 100,
            'index_only' => 60,
            default => 25,
        };

        $qualityBoost = (int) round(max(0, min(1, (float) ($face->quality_score ?? 0))) * 20);
        $areaBoost = (int) round(max(0, min(1, (float) ($face->face_area_ratio ?? 0))) * 10);
        $primaryBoost = $face->is_primary_face_candidate ? 5 : 0;

        return $tierBase + $qualityBoost + $areaBoost + $primaryBoost;
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingPayload(EventMediaFace $face): array
    {
        return [
            'label' => 'Quem e esta pessoa?',
            'question' => 'Quem e esta pessoa?',
            'event_media_id' => $face->event_media_id,
            'face_index' => $face->face_index,
            'quality_tier' => $face->quality_tier,
            'quality_score' => $face->quality_score,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedPayload(EventMediaFace $face, int $eventPersonId): array
    {
        return [
            'label' => 'Quem e esta pessoa?',
            'question' => 'Quem e esta pessoa?',
            'resolution' => 'confirmed',
            'event_media_id' => $face->event_media_id,
            'face_index' => $face->face_index,
            'event_person_id' => $eventPersonId,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidatePeople
     * @return array<string, mixed>
     */
    private function conflictPayload(EventMediaFace $face, array $candidatePeople, int $currentPersonId): array
    {
        return [
            'label' => 'Essa identidade precisa ser revisada?',
            'question' => 'Essas pessoas representam a mesma identidade?',
            'event_media_id' => $face->event_media_id,
            'face_index' => $face->face_index,
            'current_person_id' => $currentPersonId,
            'candidate_people' => $candidatePeople,
        ];
    }

    private function conflictQueueKey(EventMediaFace $face): string
    {
        return 'identity-conflict:' . $face->id;
    }

    private function unknownQueueKey(EventMediaFace $face): string
    {
        return 'unknown-face:' . $face->id;
    }
}
