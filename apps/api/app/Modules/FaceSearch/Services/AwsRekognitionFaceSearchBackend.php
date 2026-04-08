<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use LogicException;

class AwsRekognitionFaceSearchBackend implements FaceSearchBackendInterface
{
    public function __construct(
        private readonly AwsRekognitionClientFactory $clients,
    ) {}

    public function key(): string
    {
        return 'aws_rekognition';
    }

    public function ensureEventBackend(Event $event, EventFaceSearchSetting $settings): array
    {
        throw new LogicException('AWS Rekognition backend provisioning will be implemented in a later phase.');
    }

    public function searchBySelfie(
        Event $event,
        EventFaceSearchSetting $settings,
        EventMedia $probeMedia,
        string $binary,
        DetectedFaceData $face,
        int $topK,
    ): array {
        throw new LogicException('AWS Rekognition selfie search will be implemented in a later phase.');
    }

    public function healthCheck(Event $event, EventFaceSearchSetting $settings): array
    {
        throw new LogicException('AWS Rekognition health checks will be implemented in a later phase.');
    }

    public function deleteEventBackend(Event $event, EventFaceSearchSetting $settings): void
    {
        throw new LogicException('AWS Rekognition backend teardown will be implemented in a later phase.');
    }
}
