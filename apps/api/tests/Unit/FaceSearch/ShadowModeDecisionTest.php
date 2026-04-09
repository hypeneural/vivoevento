<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\FaceSearchBackendInterface;
use App\Modules\FaceSearch\Services\FaceSearchRouter;
use App\Modules\MediaProcessing\Models\EventMedia;

it('builds a rich shadow comparison payload with divergence metrics', function () {
    $local = new class implements FaceSearchBackendInterface
    {
        public function key(): string
        {
            return 'local_pgvector';
        }

        public function ensureEventBackend(Event $event, EventFaceSearchSetting $settings): array
        {
            return [];
        }

        public function indexMedia(EventMedia $media, EventFaceSearchSetting $settings): array
        {
            return [];
        }

        public function searchBySelfie(Event $event, EventFaceSearchSetting $settings, EventMedia $probeMedia, string $binary, DetectedFaceData $face, int $topK): array
        {
            return [
                'matches' => [
                    new FaceSearchMatchData(faceId: 2, eventMediaId: 2002, distance: 0.22),
                    new FaceSearchMatchData(faceId: 3, eventMediaId: 3003, distance: 0.30),
                ],
                'provider_payload_json' => ['provider' => 'local-shadow'],
            ];
        }

        public function healthCheck(Event $event, EventFaceSearchSetting $settings): array
        {
            return [];
        }

        public function deleteEventBackend(Event $event, EventFaceSearchSetting $settings): void {}
    };

    $aws = new class implements FaceSearchBackendInterface
    {
        public function key(): string
        {
            return 'aws_rekognition';
        }

        public function ensureEventBackend(Event $event, EventFaceSearchSetting $settings): array
        {
            return [];
        }

        public function indexMedia(EventMedia $media, EventFaceSearchSetting $settings): array
        {
            return [];
        }

        public function searchBySelfie(Event $event, EventFaceSearchSetting $settings, EventMedia $probeMedia, string $binary, DetectedFaceData $face, int $topK): array
        {
            return [
                'matches' => [
                    new FaceSearchMatchData(faceId: 1, eventMediaId: 1001, distance: 0.08),
                    new FaceSearchMatchData(faceId: 2, eventMediaId: 2002, distance: 0.15),
                ],
                'provider_payload_json' => ['provider' => 'aws'],
            ];
        }

        public function healthCheck(Event $event, EventFaceSearchSetting $settings): array
        {
            return [];
        }

        public function deleteEventBackend(Event $event, EventFaceSearchSetting $settings): void {}
    };

    $router = new FaceSearchRouter([$local, $aws]);
    $settings = new EventFaceSearchSetting(array_merge(
        EventFaceSearchSetting::defaultAttributes(),
        [
            'search_backend_key' => 'aws_rekognition',
            'recognition_enabled' => true,
            'routing_policy' => 'aws_primary_local_shadow',
            'fallback_backend_key' => 'local_pgvector',
            'shadow_mode_percentage' => 100,
        ],
    ));

    $event = Event::factory()->make();
    $probeMedia = EventMedia::factory()->make(['event_id' => 1]);
    $face = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 10, 100, 100),
        qualityScore: 0.9,
    );

    $result = $router->executeSelfieSearch($event, $settings, $probeMedia, 'binary', $face, 10);

    expect($result['response_backend_key'])->toBe('aws_rekognition')
        ->and($result['primary_duration_ms'])->toBeInt()
        ->and($result['response_duration_ms'])->toBeInt()
        ->and($result['shadow'])->toMatchArray([
            'backend_key' => 'local_pgvector',
            'status' => 'completed',
            'result_count' => 2,
        ])
        ->and($result['shadow']['comparison'])->toMatchArray([
            'primary_result_count' => 2,
            'shadow_result_count' => 2,
            'shared_count' => 1,
            'shared_event_media_ids' => [2002],
            'primary_only_event_media_ids' => [1001],
            'shadow_only_event_media_ids' => [3003],
            'top_match_same' => false,
            'divergence_ratio' => 0.666667,
        ]);
});
