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
     * @return array{
     *   status:string,
     *   source_ref:string|null,
     *   faces_detected:int,
     *   faces_indexed:int,
     *   skipped_reason:string|null,
     *   quality_summary?:array<string,int>,
     *   dominant_rejection_reason?:string|null
     * }
     */
    public function indexMedia(EventMedia $media, EventFaceSearchSetting $settings): array;

    /**
     * @return array{
     *   matches:array<int, FaceSearchMatchData>,
     *   provider_payload_json?:array<string, mixed>
     * }
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
