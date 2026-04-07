<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use RuntimeException;

class CompreFaceEmbeddingProvider implements FaceEmbeddingProviderInterface
{
    public function embed(
        EventMedia $media,
        EventFaceSearchSetting $settings,
        string $cropBinary,
        DetectedFaceData $face,
    ): FaceEmbeddingData {
        $vector = $this->validatedEmbedding($face);
        $dimension = count($vector);
        $expectedDimension = max(1, (int) config('face_search.embedding_dimension', 512));

        if ($dimension !== $expectedDimension) {
            throw new RuntimeException(sprintf(
                'CompreFace embedding dimension [%d] does not match configured FACE_SEARCH_EMBEDDING_DIMENSION [%d].',
                $dimension,
                $expectedDimension,
            ));
        }

        $config = (array) config('face_search.providers.compreface', []);
        $providerVersion = (string) ($config['provider_version'] ?? 'compreface-rest-v1');
        $modelKey = (string) ($config['model'] ?? ($settings->embedding_model_key ?: 'compreface-face-v1'));
        $modelSnapshot = (string) ($config['model_snapshot'] ?? $modelKey);

        if ($modelSnapshot === '') {
            $modelSnapshot = $modelKey;
        }

        return new FaceEmbeddingData(
            vector: $vector,
            providerKey: 'compreface',
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            embeddingVersion: $providerVersion,
            rawResponse: [
                'provider' => 'compreface',
                'provider_version' => $providerVersion,
                'model_key' => $modelKey,
                'model_snapshot' => $modelSnapshot,
                'embedding_dimension' => $dimension,
                'provider_payload' => $face->providerPayload,
                'event_media_id' => $media->id,
            ],
        );
    }

    /**
     * @return array<int, float>
     */
    private function validatedEmbedding(DetectedFaceData $face): array
    {
        if ($face->providerEmbedding === []) {
            throw new RuntimeException('CompreFace calculator did not return a usable embedding.');
        }

        $vector = [];

        foreach ($face->providerEmbedding as $value) {
            if (! is_numeric($value)) {
                throw new RuntimeException('CompreFace calculator returned a non-numeric embedding value.');
            }

            $vector[] = (float) $value;
        }

        if ($this->isZeroVector($vector)) {
            throw new RuntimeException('CompreFace calculator returned a zero embedding.');
        }

        return $vector;
    }

    /**
     * @param array<int, float> $vector
     */
    private function isZeroVector(array $vector): bool
    {
        foreach ($vector as $value) {
            if (abs($value) > 0.0) {
                return false;
            }
        }

        return true;
    }
}
