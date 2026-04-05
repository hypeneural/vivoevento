<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use InvalidArgumentException;

class FaceEmbeddingProviderManager implements FaceEmbeddingProviderInterface
{
    /**
     * @param array<string, FaceEmbeddingProviderInterface> $providers
     */
    public function __construct(
        private readonly array $providers,
    ) {}

    public function embed(
        EventMedia $media,
        EventFaceSearchSetting $settings,
        string $cropBinary,
        DetectedFaceData $face,
    ): FaceEmbeddingData {
        return $this->resolve($settings->provider_key)->embed($media, $settings, $cropBinary, $face);
    }

    public function resolve(?string $providerKey): FaceEmbeddingProviderInterface
    {
        $resolvedKey = $providerKey ?: (string) config('face_search.default_embedding_provider', 'noop');

        if (array_key_exists($resolvedKey, $this->providers)) {
            return $this->providers[$resolvedKey];
        }

        throw new InvalidArgumentException("Unsupported face embedding provider [{$resolvedKey}].");
    }
}
