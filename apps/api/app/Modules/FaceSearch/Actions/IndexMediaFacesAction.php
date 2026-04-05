<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\FaceSearch\Services\FaceQualityGateService;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;

class IndexMediaFacesAction
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
        private readonly FaceEmbeddingProviderInterface $embedder,
        private readonly FaceVectorStoreInterface $vectorStore,
        private readonly FaceQualityGateService $qualityGate,
    ) {}

    /**
     * @return array{
     *   status:string,
     *   source_ref:string|null,
     *   faces_detected:int,
     *   faces_indexed:int,
     *   skipped_reason:string|null
     * }
     */
    public function execute(EventMedia $media): array
    {
        $media->loadMissing(['event.faceSearchSettings', 'variants']);
        $settings = $media->event?->faceSearchSettings;

        if (! $media->event || ! $settings || ! $settings->enabled) {
            return [
                'status' => 'skipped',
                'source_ref' => null,
                'faces_detected' => 0,
                'faces_indexed' => 0,
                'skipped_reason' => 'face_search_disabled',
            ];
        }

        if ($media->media_type !== 'image') {
            return [
                'status' => 'skipped',
                'source_ref' => null,
                'faces_detected' => 0,
                'faces_indexed' => 0,
                'skipped_reason' => 'unsupported_media_type',
            ];
        }

        [$sourceDisk, $sourcePath, $binary] = $this->loadSourceBinary($media);
        $detectedFaces = $this->detector->detect($media, $settings, $binary);

        if ($detectedFaces === []) {
            $this->cleanupIndexedFaces($media);

            return [
                'status' => 'skipped',
                'source_ref' => "{$sourceDisk}:{$sourcePath}",
                'faces_detected' => 0,
                'faces_indexed' => 0,
                'skipped_reason' => 'no_faces_detected',
            ];
        }

        $this->cleanupIndexedFaces($media);

        $indexedFaces = 0;
        $primaryFaceId = null;
        $primaryFaceQuality = -1.0;
        $sourceImage = Image::decode($binary);

        foreach (array_values($detectedFaces) as $index => $face) {
            if (! $this->qualityGate->passes($face, $settings)) {
                continue;
            }

            $cropBinary = $this->cropFace($binary, $face);
            $cropPath = "events/{$media->event_id}/faces/{$media->id}/face-{$index}.webp";
            $cropDisk = (string) config('face_search.crop_disk', 'ai-private');

            Storage::disk($cropDisk)->put($cropPath, $cropBinary);

            $embedding = $this->embedder->embed($media, $settings, $cropBinary, $face);

            $faceModel = EventMediaFace::query()->create([
                'event_id' => $media->event_id,
                'event_media_id' => $media->id,
                'face_index' => $index,
                'bbox_x' => $face->boundingBox->x,
                'bbox_y' => $face->boundingBox->y,
                'bbox_w' => $face->boundingBox->width,
                'bbox_h' => $face->boundingBox->height,
                'detection_confidence' => $face->detectionConfidence,
                'quality_score' => $face->qualityScore,
                'sharpness_score' => $face->sharpnessScore,
                'face_area_ratio' => $face->faceAreaRatio ?? $this->faceAreaRatio($sourceImage->width(), $sourceImage->height(), $face),
                'pose_yaw' => $face->poseYaw,
                'pose_pitch' => $face->posePitch,
                'pose_roll' => $face->poseRoll,
                'searchable' => $media->moderation_status !== ModerationStatus::Rejected,
                'crop_disk' => $cropDisk,
                'crop_path' => $cropPath,
                'embedding_model_key' => $embedding->modelKey ?? $settings->embedding_model_key,
                'embedding_version' => $embedding->embeddingVersion ?? 'foundation-v1',
                'vector_store_key' => $settings->vector_store_key,
                'face_hash' => hash('sha256', $cropBinary),
                'is_primary_face_candidate' => $face->isPrimaryCandidate,
            ]);

            $this->vectorStore->upsert($faceModel, $embedding);

            $indexedFaces++;

            if ($face->isPrimaryCandidate || $face->qualityScore > $primaryFaceQuality) {
                $primaryFaceId = $faceModel->id;
                $primaryFaceQuality = $face->qualityScore;
            }
        }

        if ($primaryFaceId !== null) {
            EventMediaFace::query()
                ->where('event_media_id', $media->id)
                ->update([
                    'is_primary_face_candidate' => false,
                ]);

            EventMediaFace::query()
                ->whereKey($primaryFaceId)
                ->update([
                    'is_primary_face_candidate' => true,
                ]);
        }

        return [
            'status' => $indexedFaces > 0 ? 'indexed' : 'skipped',
            'source_ref' => "{$sourceDisk}:{$sourcePath}",
            'faces_detected' => count($detectedFaces),
            'faces_indexed' => $indexedFaces,
            'skipped_reason' => $indexedFaces > 0 ? null : 'no_faces_after_quality_gate',
        ];
    }

    private function cleanupIndexedFaces(EventMedia $media): void
    {
        $media->loadMissing('faces');

        foreach ($media->faces as $face) {
            $this->vectorStore->delete($face);

            if ($face->crop_disk && $face->crop_path) {
                Storage::disk($face->crop_disk)->delete($face->crop_path);
            }
        }

        $media->faces()->delete();
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function loadSourceBinary(EventMedia $media): array
    {
        foreach (['gallery', 'wall', 'fast_preview'] as $variantKey) {
            $variant = $media->variants->firstWhere('variant_key', $variantKey);

            if ($variant?->path && Storage::disk($variant->disk ?: 'public')->exists($variant->path)) {
                return [$variant->disk ?: 'public', $variant->path, Storage::disk($variant->disk ?: 'public')->get($variant->path)];
            }
        }

        $path = $media->originalStoragePath();
        $disk = $media->originalStorageDisk();

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Arquivo fonte nao encontrado para indexacao facial.');
        }

        return [$disk, $path, Storage::disk($disk)->get($path)];
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

    private function faceAreaRatio(
        int $imageWidth,
        int $imageHeight,
        DetectedFaceData $face,
    ): float {
        $imageArea = max(1, $imageWidth * $imageHeight);

        return $face->boundingBox->area() / $imageArea;
    }
}
