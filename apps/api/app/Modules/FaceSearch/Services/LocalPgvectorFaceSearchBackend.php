<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Intervention\Image\Laravel\Facades\Image;

class LocalPgvectorFaceSearchBackend implements FaceSearchBackendInterface
{
    public function __construct(
        private readonly FaceEmbeddingProviderInterface $embedder,
        private readonly FaceVectorStoreInterface $vectorStore,
    ) {}

    public function key(): string
    {
        return 'local_pgvector';
    }

    public function ensureEventBackend(Event $event, EventFaceSearchSetting $settings): array
    {
        return [
            'backend_key' => $this->key(),
            'status' => 'ready',
        ];
    }

    public function searchBySelfie(
        Event $event,
        EventFaceSearchSetting $settings,
        EventMedia $probeMedia,
        string $binary,
        DetectedFaceData $face,
        int $topK,
    ): array {
        $cropBinary = $this->cropFace($binary, $face);
        $embedding = $this->embedder->embed($probeMedia, $settings, $cropBinary, $face);

        return $this->vectorStore->search(
            eventId: $event->id,
            queryEmbedding: $embedding->vector,
            topK: $topK,
            threshold: $settings->search_threshold,
            searchableOnly: true,
            searchStrategy: $settings->search_strategy ?: (string) config('face_search.default_search_strategy', 'exact'),
        );
    }

    public function healthCheck(Event $event, EventFaceSearchSetting $settings): array
    {
        return [
            'backend_key' => $this->key(),
            'status' => 'healthy',
        ];
    }

    public function deleteEventBackend(Event $event, EventFaceSearchSetting $settings): void
    {
        // Local backend does not require remote teardown.
    }

    private function cropFace(string $binary, DetectedFaceData $face): string
    {
        $cropped = Image::decode($binary)->crop(
            $face->boundingBox->width,
            $face->boundingBox->height,
            $face->boundingBox->x,
            $face->boundingBox->y,
        );

        return (string) $cropped->encodeUsingMediaType('image/webp', 88);
    }
}
