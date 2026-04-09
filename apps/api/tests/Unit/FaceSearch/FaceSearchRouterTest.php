<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\FaceSearchBackendInterface;
use App\Modules\FaceSearch\Services\FaceSearchRouter;
use App\Modules\MediaProcessing\Models\EventMedia;

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
