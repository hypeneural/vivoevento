<?php

namespace App\Modules\EventPeople\Jobs;

use App\Modules\EventPeople\Actions\ProjectEventPersonRepresentativeFacesAction;
use App\Modules\EventPeople\Enums\EventPersonRepresentativeSyncStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\EventPeople\Services\EventPersonAwsProviderFaceResolver;
use App\Modules\EventPeople\Support\EventPeopleStateMachine;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\EventPeople\Support\EventPeopleQueues;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEventPersonRepresentativeFacesJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public int $backoff = 20;
    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $eventId,
        public readonly int $eventPersonId,
    ) {
        $this->onConnection('redis');
        $this->onQueue(EventPeopleQueues::low());
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return sprintf('event-people-representatives-sync:%d:%d', $this->eventId, $this->eventPersonId);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('event-people-aws-sync'),
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(300),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'event-people',
            'event-people:aws-sync',
            'event:' . $this->eventId,
            'event-person:' . $this->eventPersonId,
        ];
    }

    public function handle(): void
    {
        /** @var Event|null $event */
        $event = Event::query()
            ->with('faceSearchSettings')
            ->find($this->eventId);
        /** @var EventPerson|null $person */
        $person = EventPerson::query()
            ->with(['assignments.face', 'representativeFaces.face'])
            ->find($this->eventPersonId);

        if (! $event || ! $person || (int) $person->event_id !== (int) $event->id) {
            return;
        }

        /** @var ProjectEventPersonRepresentativeFacesAction $projector */
        $projector = app(ProjectEventPersonRepresentativeFacesAction::class);
        /** @var EventPersonAwsProviderFaceResolver $resolver */
        $resolver = app(EventPersonAwsProviderFaceResolver::class);
        /** @var AwsRekognitionFaceSearchBackend $backend */
        $backend = app(AwsRekognitionFaceSearchBackend::class);
        /** @var EventPeopleStateMachine $stateMachine */
        $stateMachine = app(EventPeopleStateMachine::class);

        $representatives = $projector->execute($event, $person);

        if ($representatives->isEmpty()) {
            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->info('event_people.representative_faces.projected_empty', [
                    'event_id' => $this->eventId,
                    'event_person_id' => $this->eventPersonId,
                    'queue' => $this->queue ?: EventPeopleQueues::low(),
                ]);

            return;
        }

        $settings = $event->faceSearchSettings;

        if (! $settings?->enabled || ! $settings->recognition_enabled || $settings->search_backend_key !== 'aws_rekognition') {
            $this->markRepresentatives($representatives, EventPersonRepresentativeSyncStatus::Skipped, [
                'reason' => 'aws_backend_not_enabled',
            ]);

            return;
        }

        $backend->ensureEventBackend($event, $settings);
        $providerFaceIdsByLocalFace = $resolver->resolveFaceIds($event->id, $settings, $representatives);
        $providerFaceIds = array_values(array_unique(array_filter($providerFaceIdsByLocalFace, 'is_string')));

        if ($providerFaceIds === []) {
            $this->markRepresentatives($representatives, EventPersonRepresentativeSyncStatus::Failed, [
                'reason' => 'provider_face_ids_not_found',
            ]);

            return;
        }

        $result = $backend->syncUserVector(
            $event,
            $settings,
            sprintf('evt:%d:person:%d', $event->id, $person->id),
            $providerFaceIds,
        );

        $syncedAt = now();

        foreach ($representatives as $representative) {
            $mappedFaceId = $providerFaceIdsByLocalFace[(int) $representative->event_media_face_id] ?? null;

            $stateMachine->transitionRepresentativeSync(
                $representative,
                is_string($mappedFaceId) && $mappedFaceId !== ''
                    ? EventPersonRepresentativeSyncStatus::Synced
                    : EventPersonRepresentativeSyncStatus::Failed,
                [
                    'last_synced_at' => is_string($mappedFaceId) && $mappedFaceId !== '' ? $syncedAt : null,
                    'sync_payload' => [
                        'result' => $result,
                        'provider_face_id' => $mappedFaceId,
                        'local_face_id' => $representative->event_media_face_id,
                    ],
                ],
            );
        }

        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->info('event_people.representative_faces.synced', [
                'event_id' => $this->eventId,
                'event_person_id' => $this->eventPersonId,
                'representative_count' => $representatives->count(),
                'provider_face_count' => count($providerFaceIds),
                'queue' => $this->queue ?: EventPeopleQueues::low(),
            ]);
    }

    public function failed(Throwable $exception): void
    {
        $representatives = EventPersonRepresentativeFace::query()
            ->where('event_id', $this->eventId)
            ->where('event_person_id', $this->eventPersonId)
            ->get();

        $this->markRepresentatives($representatives, EventPersonRepresentativeSyncStatus::Failed, [
            'reason' => 'job_failed',
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * @param  iterable<int, EventPersonRepresentativeFace>  $representatives
     * @param  array<string, mixed>  $payload
     */
    private function markRepresentatives(iterable $representatives, EventPersonRepresentativeSyncStatus $status, array $payload): void
    {
        $timestamp = $status === EventPersonRepresentativeSyncStatus::Synced ? now() : null;
        /** @var EventPeopleStateMachine $stateMachine */
        $stateMachine = app(EventPeopleStateMachine::class);

        foreach ($representatives as $representative) {
            $stateMachine->transitionRepresentativeSync($representative, $status, [
                'last_synced_at' => $timestamp,
                'sync_payload' => $payload,
            ]);
        }
    }
}
