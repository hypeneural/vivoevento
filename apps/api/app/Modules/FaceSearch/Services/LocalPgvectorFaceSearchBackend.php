<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class LocalPgvectorFaceSearchBackend implements FaceSearchBackendInterface
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
        private readonly FaceEmbeddingProviderInterface $embedder,
        private readonly FaceVectorStoreInterface $vectorStore,
        private readonly FaceQualityGateService $qualityGate,
        private readonly FaceSearchMediaSourceLoader $sourceLoader,
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

    public function indexMedia(EventMedia $media, EventFaceSearchSetting $settings): array
    {
        $source = $this->sourceLoader->loadImageBinary($media);
        $binary = $source['binary'];
        $detectedFaces = $this->detector->detect($media, $settings, $binary);

        if ($detectedFaces === []) {
            $this->cleanupIndexedFaces($media);

            return [
                'status' => 'skipped',
                'source_ref' => $source['source_ref'],
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
        $qualitySummary = [
            'reject' => 0,
            'index_only' => 0,
            'search_priority' => 0,
        ];
        $dominantRejectionReason = null;

        foreach (array_values($detectedFaces) as $index => $face) {
            $qualityAssessment = $this->qualityGate->assess($face, $settings);
            $qualitySummary[$qualityAssessment->tier->value]++;

            if ($qualityAssessment->isRejected()) {
                $dominantRejectionReason ??= $qualityAssessment->reason;

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
                'quality_tier' => $qualityAssessment->tier->value,
                'quality_rejection_reason' => $qualityAssessment->reason,
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
            'source_ref' => $source['source_ref'],
            'faces_detected' => count($detectedFaces),
            'faces_indexed' => $indexedFaces,
            'quality_summary' => $qualitySummary,
            'dominant_rejection_reason' => $dominantRejectionReason,
            'skipped_reason' => $indexedFaces > 0 ? null : 'no_faces_after_quality_gate',
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

        return [
            'matches' => $this->vectorStore->search(
                eventId: $event->id,
                queryEmbedding: $embedding->vector,
                topK: $topK,
                threshold: $settings->search_threshold,
                searchableOnly: true,
                searchStrategy: $settings->search_strategy ?: (string) config('face_search.default_search_strategy', 'exact'),
            ),
            'provider_payload_json' => null,
        ];
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
