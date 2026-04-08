<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

interface FaceSearchBackendInterface
{
    public function key(): string;

    /**
     * @return array<string, mixed>
     */
    public function ensureEventBackend(Event $event, EventFaceSearchSetting $settings): array;

    /**
     * @return array<int, FaceSearchMatchData>
     */
    public function searchBySelfie(
        Event $event,
        EventFaceSearchSetting $settings,
        EventMedia $probeMedia,
        string $binary,
        DetectedFaceData $face,
        int $topK,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function healthCheck(Event $event, EventFaceSearchSetting $settings): array;

    public function deleteEventBackend(Event $event, EventFaceSearchSetting $settings): void;
}
