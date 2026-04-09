<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceBoundingBoxData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;
use Throwable;

class CompreFaceDetectionProvider implements FaceDetectionProviderInterface
{
    public function __construct(
        private readonly CompreFaceClient $client,
    ) {}

    public function detect(
        EventMedia $media,
        EventFaceSearchSetting $settings,
        string $binary,
    ): array {
        $payload = $this->requestDetection($media, $binary);
        $results = $payload['result'] ?? [];
        $imageDimensions = $this->imageDimensions($binary);

        if (! is_array($results)) {
            throw new RuntimeException('CompreFace detection response did not contain a valid result array.');
        }

        $primaryIndex = $this->primaryFaceIndex($results);
        $faces = [];

        foreach (array_values($results) as $index => $facePayload) {
            if (! is_array($facePayload)) {
                throw new RuntimeException('CompreFace detection response contains an invalid face payload.');
            }

            $faces[] = $this->mapFace(
                facePayload: $facePayload,
                payload: $payload,
                isPrimaryCandidate: $index === $primaryIndex,
                imageDimensions: $imageDimensions,
            );
        }

        return $faces;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestDetection(EventMedia $media, string $binary): array
    {
        $config = (array) config('face_search.providers.compreface', []);
        $useBase64 = (bool) ($config['use_base64'] ?? true);

        if ($useBase64) {
            return $this->client->detectBase64(base64_encode($binary));
        }

        return $this->client->detectMultipart(
            binary: $binary,
            filename: sprintf('event-media-%s.jpg', $media->id ?: 'probe'),
            mimeType: $media->mime_type ?: 'image/jpeg',
        );
    }

    /**
     * @param array<int, mixed> $results
     */
    private function primaryFaceIndex(array $results): ?int
    {
        $primaryIndex = null;
        $primaryArea = -1;
        $primaryConfidence = -1.0;

        foreach (array_values($results) as $index => $facePayload) {
            if (! is_array($facePayload)) {
                continue;
            }

            $box = $facePayload['box'] ?? null;

            if (! is_array($box) || ! $this->hasValidBoxCoordinates($box)) {
                continue;
            }

            $width = (int) $box['x_max'] - (int) $box['x_min'];
            $height = (int) $box['y_max'] - (int) $box['y_min'];
            $area = $width * $height;
            $confidence = $this->numeric($box['probability'] ?? null, 0.0);

            if ($area > $primaryArea || ($area === $primaryArea && $confidence > $primaryConfidence)) {
                $primaryIndex = $index;
                $primaryArea = $area;
                $primaryConfidence = $confidence;
            }
        }

        return $primaryIndex;
    }

    /**
     * @param array<string, mixed> $facePayload
     * @param array<string, mixed> $payload
     * @param array{width:int,height:int}|null $imageDimensions
     */
    private function mapFace(
        array $facePayload,
        array $payload,
        bool $isPrimaryCandidate,
        ?array $imageDimensions = null,
    ): DetectedFaceData {
        $box = $facePayload['box'] ?? null;

        if (! is_array($box) || ! $this->hasValidBoxCoordinates($box)) {
            throw new RuntimeException('CompreFace detection response contains an invalid bounding box.');
        }

        $x = max(0, (int) $box['x_min']);
        $y = max(0, (int) $box['y_min']);
        $width = (int) $box['x_max'] - (int) $box['x_min'];
        $height = (int) $box['y_max'] - (int) $box['y_min'];

        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException('CompreFace detection response contains an invalid bounding box.');
        }

        $confidence = round($this->numeric($box['probability'] ?? null, 0.0), 6);
        $imageWidth = $imageDimensions['width'] ?? null;
        $imageHeight = $imageDimensions['height'] ?? null;
        $faceAreaRatio = is_int($imageWidth) && is_int($imageHeight) && $imageWidth > 0 && $imageHeight > 0
            ? ($width * $height) / ($imageWidth * $imageHeight)
            : null;

        return new DetectedFaceData(
            boundingBox: new FaceBoundingBoxData($x, $y, $width, $height),
            detectionConfidence: $confidence,
            qualityScore: $confidence,
            faceAreaRatio: $faceAreaRatio,
            isPrimaryCandidate: $isPrimaryCandidate,
            landmarks: $this->normalizeLandmarks($facePayload['landmarks'] ?? []),
            providerEmbedding: $this->normalizeEmbedding($facePayload['embedding'] ?? []),
            providerPayload: [
                'provider' => 'compreface',
                'face' => $facePayload,
                'plugins_versions' => is_array($payload['plugins_versions'] ?? null)
                    ? $payload['plugins_versions']
                    : [],
                'embedding_dimension' => is_array($facePayload['embedding'] ?? null)
                    ? count($facePayload['embedding'])
                    : null,
                'image_width' => $imageWidth,
                'image_height' => $imageHeight,
            ],
        );
    }

    /**
     * @param array<string, mixed> $box
     */
    private function hasValidBoxCoordinates(array $box): bool
    {
        foreach (['x_min', 'y_min', 'x_max', 'y_max'] as $key) {
            if (! array_key_exists($key, $box) || ! is_numeric($box[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array{x:int, y:int}>
     */
    private function normalizeLandmarks(mixed $landmarks): array
    {
        if (! is_array($landmarks)) {
            return [];
        }

        $normalized = [];

        foreach ($landmarks as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }

            $x = $point[0] ?? null;
            $y = $point[1] ?? null;

            if (! is_numeric($x) || ! is_numeric($y)) {
                continue;
            }

            $normalized[] = [
                'x' => (int) $x,
                'y' => (int) $y,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, float>
     */
    private function normalizeEmbedding(mixed $embedding): array
    {
        if (! is_array($embedding)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0,
            $embedding,
        ));
    }

    private function numeric(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return array{width:int,height:int}|null
     */
    private function imageDimensions(string $binary): ?array
    {
        try {
            $image = Image::decode($binary);

            return [
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
