<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use InvalidArgumentException;

class FaceDetectionProviderManager implements FaceDetectionProviderInterface
{
    /**
     * @param array<string, FaceDetectionProviderInterface> $providers
     */
    public function __construct(
        private readonly array $providers,
    ) {}

    public function detect(
        EventMedia $media,
        EventFaceSearchSetting $settings,
        string $binary,
    ): array {
        return $this->resolve($settings->provider_key)->detect($media, $settings, $binary);
    }

    public function resolve(?string $providerKey): FaceDetectionProviderInterface
    {
        $resolvedKey = $providerKey ?: (string) config('face_search.default_detection_provider', 'noop');

        if (array_key_exists($resolvedKey, $this->providers)) {
            return $this->providers[$resolvedKey];
        }

        throw new InvalidArgumentException("Unsupported face detection provider [{$resolvedKey}].");
    }
}
