<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonRepresentativeSyncStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectEventPersonRepresentativeFacesAction
{
    private const LIMIT = 8;

    /**
     * @return Collection<int, EventPersonRepresentativeFace>
     */
    public function execute(Event $event, EventPerson $person): Collection
    {
        $person->loadMissing('assignments.face');

        $candidates = $person->assignments
            ->filter(fn ($assignment): bool => (string) ($assignment->status?->value ?? $assignment->status) === EventPersonAssignmentStatus::Confirmed->value)
            ->map(fn ($assignment): ?array => $this->mapCandidate($assignment->face))
            ->filter()
            ->sortByDesc('rank_score')
            ->values();

        $selected = collect();
        $usedContextHashes = [];

        foreach ($candidates as $candidate) {
            if ($selected->count() >= self::LIMIT) {
                break;
            }

            if (in_array($candidate['context_hash'], $usedContextHashes, true)) {
                continue;
            }

            $selected->push($candidate);
            $usedContextHashes[] = $candidate['context_hash'];
        }

        return DB::transaction(function () use ($event, $person, $selected): Collection {
            $existing = EventPersonRepresentativeFace::query()
                ->where('event_id', $event->id)
                ->where('event_person_id', $person->id)
                ->get()
                ->keyBy('event_media_face_id');

            $selectedIds = $selected->pluck('event_media_face_id')->all();
            $currentIds = $existing->keys()->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
            $sortedSelectedIds = collect($selectedIds)->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
            $selectionChanged = $currentIds !== $sortedSelectedIds;

            if ($selectedIds === []) {
                EventPersonRepresentativeFace::query()
                    ->where('event_id', $event->id)
                    ->where('event_person_id', $person->id)
                    ->delete();

                return collect();
            }

            foreach ($selected as $candidate) {
                /** @var EventPersonRepresentativeFace|null $row */
                $row = $existing->get($candidate['event_media_face_id']);
                $syncStatus = $selectionChanged
                    ? EventPersonRepresentativeSyncStatus::Pending
                    : ($row?->sync_status ?? EventPersonRepresentativeSyncStatus::Pending);

                EventPersonRepresentativeFace::query()->updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'event_person_id' => $person->id,
                        'event_media_face_id' => $candidate['event_media_face_id'],
                    ],
                    [
                        'rank_score' => $candidate['rank_score'],
                        'quality_score' => $candidate['quality_score'],
                        'pose_bucket' => $candidate['pose_bucket'],
                        'context_hash' => $candidate['context_hash'],
                        'sync_status' => $syncStatus->value,
                        'last_synced_at' => $selectionChanged ? null : $row?->last_synced_at,
                        'sync_payload' => $selectionChanged ? null : $row?->sync_payload,
                    ],
                );
            }

            EventPersonRepresentativeFace::query()
                ->where('event_id', $event->id)
                ->where('event_person_id', $person->id)
                ->whereNotIn('event_media_face_id', $selectedIds)
                ->delete();

            return EventPersonRepresentativeFace::query()
                ->with(['face.media'])
                ->where('event_id', $event->id)
                ->where('event_person_id', $person->id)
                ->orderByDesc('rank_score')
                ->get();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapCandidate(?EventMediaFace $face): ?array
    {
        if (! $face || ! $face->searchable) {
            return null;
        }

        $quality = max(0.0, (float) ($face->quality_score ?? 0));
        $area = max(0.0, (float) ($face->face_area_ratio ?? 0));
        $yaw = (float) ($face->pose_yaw ?? 0);
        $pitch = (float) ($face->pose_pitch ?? 0);
        $frontalityPenalty = (abs($yaw) / 90) + (abs($pitch) / 90);
        $rankScore = round(($quality * 100) + ($area * 10) + ($face->is_primary_face_candidate ? 5 : 0) - $frontalityPenalty, 4);
        $poseBucket = $this->poseBucket($yaw, $pitch);

        return [
            'event_media_face_id' => (int) $face->id,
            'rank_score' => $rankScore,
            'quality_score' => $quality > 0 ? round($quality, 4) : null,
            'pose_bucket' => $poseBucket,
            'context_hash' => sha1(sprintf('media:%d|pose:%s', (int) $face->event_media_id, $poseBucket)),
        ];
    }

    private function poseBucket(float $yaw, float $pitch): string
    {
        $yawBucket = abs($yaw) < 15 ? 'center' : ($yaw > 0 ? 'right' : 'left');
        $pitchBucket = abs($pitch) < 12 ? 'level' : ($pitch > 0 ? 'down' : 'up');

        return "{$yawBucket}-{$pitchBucket}";
    }
}
