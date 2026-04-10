<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\FaceSearchBackendInterface;
use App\Modules\FaceSearch\Services\FaceSearchRouter;
use App\Modules\FaceSearch\Services\FaceSearchTelemetryService;
use App\Modules\MediaProcessing\Models\EventMedia;
use Aws\Command;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery as m;

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

    $telemetry = m::mock(FaceSearchTelemetryService::class);
    $telemetry->shouldReceive('recordRouterFallbackTriggered')
        ->once()
        ->withArgs(function (Event $resolvedEvent, EventFaceSearchSetting $resolvedSettings, array $payload): bool {
            return $resolvedEvent->exists === false
                && $resolvedSettings->routing_policy === 'aws_primary_local_fallback'
                && ($payload['primary_backend_key'] ?? null) === 'aws_rekognition'
                && ($payload['response_backend_key'] ?? null) === 'local_pgvector'
                && ($payload['primary_failure']['reason_code'] ?? null) === 'throttled';
        });

    $router = new FaceSearchRouter([$local, $aws], telemetry: $telemetry);
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

it('indexes a mandatory local baseline when aws shadow routing is enabled even if query shadow sampling is zero', function () {
    Storage::fake('public');
    Storage::disk('public')->put('events/1/variants/1/gallery.jpg', 'gallery-binary');

    $calls = (object) [
        'aws' => 0,
        'local' => 0,
    ];

    $local = new class($calls) implements FaceSearchBackendInterface
    {
        public function __construct(private readonly object $calls) {}

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
            $this->calls->local++;

            return [
                'status' => 'indexed',
                'source_ref' => 'public:events/1/variants/1/gallery.jpg',
                'faces_detected' => 1,
                'faces_indexed' => 1,
                'skipped_reason' => null,
            ];
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

    $aws = new class($calls) implements FaceSearchBackendInterface
    {
        public function __construct(private readonly object $calls) {}

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
            $this->calls->aws++;

            return [
                'status' => 'indexed',
                'source_ref' => 'aws:collection/event-1',
                'faces_detected' => 2,
                'faces_indexed' => 2,
                'skipped_reason' => null,
            ];
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
            'routing_policy' => 'aws_primary_local_shadow',
            'fallback_backend_key' => 'local_pgvector',
            'shadow_mode_percentage' => 0,
        ],
    ));

    $media = EventMedia::factory()->make();
    $media->setRelation('variants', collect([
        (object) [
            'variant_key' => 'gallery',
            'disk' => 'public',
            'path' => 'events/1/variants/1/gallery.jpg',
        ],
    ]));

    $result = $router->indexMedia($media, $settings);

    expect($calls->aws)->toBe(1)
        ->and($calls->local)->toBe(1)
        ->and($result['status'])->toBe('indexed')
        ->and($result['shadow'])->toMatchArray([
            'backend_key' => 'local_pgvector',
            'status' => 'completed',
            'baseline_required' => true,
        ])
        ->and(data_get($result, 'shadow.result.source_ref'))->toBe('public:events/1/variants/1/gallery.jpg');
});
