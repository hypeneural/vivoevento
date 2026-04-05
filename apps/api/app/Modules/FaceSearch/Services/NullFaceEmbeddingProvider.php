<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

class NullFaceEmbeddingProvider implements FaceEmbeddingProviderInterface
{
    public function embed(
        EventMedia $media,
        EventFaceSearchSetting $settings,
        string $cropBinary,
        DetectedFaceData $face,
    ): FaceEmbeddingData {
        $dimension = max(1, (int) config('face_search.embedding_dimension', 512));

        return new FaceEmbeddingData(
            vector: array_fill(0, $dimension, 0.0),
            providerKey: 'noop',
            providerVersion: (string) config('face_search.providers.noop.provider_version', 'foundation-v1'),
            modelKey: (string) config('face_search.providers.noop.model', 'noop-face-v1'),
            modelSnapshot: (string) config('face_search.providers.noop.model_snapshot', 'noop-face-v1'),
            embeddingVersion: 'foundation-v1',
            rawResponse: [
                'provider' => 'noop',
                'event_media_id' => $media->id,
            ],
        );
    }
}
