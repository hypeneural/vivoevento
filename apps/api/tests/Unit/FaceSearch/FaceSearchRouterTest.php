<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\FaceSearchBackendInterface;
use App\Modules\FaceSearch\Services\FaceSearchRouter;
use App\Modules\MediaProcessing\Models\EventMedia;
use Aws\Command;
use Aws\Exception\AwsException;
use Illuminate\Validation\ValidationException;

it('routes to the configured aws backend when recognition is enabled', function () {
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
            return [];
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
            return [];
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
        ],
    ));

    expect($router->backendForSettings($settings)->key())->toBe('aws_rekognition');
});

it('falls back to local pgvector when aws is selected without recognition enabled', function () {
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
            return [];
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
            return [];
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
            'recognition_enabled' => false,
        ],
    ));

    expect($router->backendForSettings($settings)->key())->toBe('local_pgvector');
});

it('falls back to local search when the aws primary fails with a transient provider error', function () {
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
                    new FaceSearchMatchData(
                        faceId: 1,
                        eventMediaId: 101,
                        distance: 0.12,
                    ),
                ],
                'provider_payload_json' => ['provider' => 'local'],
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
            throw new AwsException('throttled', new Command('SearchFacesByImage'), [
                'code' => 'ThrottlingException',
            ]);
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
            'routing_policy' => 'aws_primary_local_fallback',
            'fallback_backend_key' => 'local_pgvector',
        ],
    ));

    $event = Event::factory()->make();
    $probeMedia = EventMedia::factory()->make(['event_id' => 1]);
    $face = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 10, 100, 100),
        qualityScore: 0.9,
    );

    $result = $router->executeSelfieSearch($event, $settings, $probeMedia, 'binary', $face, 10);

    expect($result['response_backend_key'])->toBe('local_pgvector')
        ->and($result['fallback_triggered'])->toBeTrue()
        ->and($result['query_status'])->toBe('degraded')
        ->and($result['primary_failure']['reason_code'])->toBe('throttled')
        ->and($result['matches'])->toHaveCount(1);
});

it('does not fall back when the primary backend fails with a permanent functional error', function () {
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
            throw new RuntimeException('local should not be called');
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
            throw ValidationException::withMessages([
                'selfie' => ['Envie uma selfie com apenas uma pessoa visivel. Busca por foto de grupo ainda nao faz parte desta versao.'],
            ]);
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
            'routing_policy' => 'aws_primary_local_fallback',
            'fallback_backend_key' => 'local_pgvector',
        ],
    ));

    $event = Event::factory()->make();
    $probeMedia = EventMedia::factory()->make(['event_id' => 1]);
    $face = new DetectedFaceData(
        boundingBox: new FaceBoundingBoxData(10, 10, 100, 100),
        qualityScore: 0.9,
    );

    expect(fn () => $router->executeSelfieSearch($event, $settings, $probeMedia, 'binary', $face, 10))
        ->toThrow(ValidationException::class);
});

it('runs the configured local shadow backend without changing the aws primary response', function () {
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
                    new FaceSearchMatchData(
                        faceId: 2,
                        eventMediaId: 202,
                        distance: 0.25,
                    ),
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
                    new FaceSearchMatchData(
                        faceId: 1,
                        eventMediaId: 101,
                        distance: 0.08,
                    ),
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
        ->and($result['fallback_triggered'])->toBeFalse()
        ->and($result['matches'][0]->eventMediaId)->toBe(101)
        ->and($result['shadow'])->toMatchArray([
            'backend_key' => 'local_pgvector',
            'status' => 'completed',
            'result_count' => 1,
        ]);
});
