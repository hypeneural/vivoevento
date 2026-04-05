<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\FaceSearch\Models\EventFaceSearchRequest;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use Illuminate\Support\Facades\Storage;

class CleanupDeletedMediaArtifactsAction
{
    public function __construct(
        private readonly FaceVectorStoreInterface $vectorStore,
    ) {}

    /**
     * @return array{
     *   variants_deleted:int,
     *   face_crops_deleted:int,
     *   vectors_deleted:int,
     *   face_rows_deleted:int,
     *   safety_evaluations_deleted:int,
     *   vlm_evaluations_deleted:int,
     *   search_requests_updated:int,
     *   original_deleted:bool
     * }
     */
    public function execute(EventMedia $eventMedia): array
    {
        $eventMedia->loadMissing(['variants', 'faces', 'safetyEvaluations', 'vlmEvaluations']);

        $originalDeleted = false;
        $variantsDeleted = 0;
        $faceCropsDeleted = 0;
        $vectorsDeleted = 0;

        $originalPath = $eventMedia->originalStoragePath();
        $originalDisk = $eventMedia->originalStorageDisk();

        if ($originalPath && Storage::disk($originalDisk)->exists($originalPath)) {
            $originalDeleted = (bool) Storage::disk($originalDisk)->delete($originalPath);
        }

        foreach ($eventMedia->variants as $variant) {
            if ($variant->path && Storage::disk($variant->disk ?: 'public')->exists($variant->path)) {
                Storage::disk($variant->disk ?: 'public')->delete($variant->path);
            }

            $variantsDeleted++;
        }

        foreach ($eventMedia->faces as $face) {
            $this->vectorStore->delete($face);
            $vectorsDeleted++;

            if ($face->crop_path && Storage::disk($face->crop_disk ?: (string) config('face_search.crop_disk', 'ai-private'))->exists($face->crop_path)) {
                Storage::disk($face->crop_disk ?: (string) config('face_search.crop_disk', 'ai-private'))->delete($face->crop_path);
                $faceCropsDeleted++;
            }
        }

        EventMediaVariant::query()->where('event_media_id', $eventMedia->id)->delete();
        $faceRowsDeleted = EventMediaFace::query()->where('event_media_id', $eventMedia->id)->delete();
        $safetyEvaluationsDeleted = $eventMedia->safetyEvaluations()->delete();
        $vlmEvaluationsDeleted = EventMediaVlmEvaluation::query()->where('event_media_id', $eventMedia->id)->delete();

        $searchRequestsUpdated = 0;

        EventFaceSearchRequest::query()
            ->where('event_id', $eventMedia->event_id)
            ->whereNotNull('result_photo_ids_json')
            ->get()
            ->each(function (EventFaceSearchRequest $request) use ($eventMedia, &$searchRequestsUpdated): void {
                $resultIds = array_values(array_filter(
                    array_map(static fn ($value) => (int) $value, $request->result_photo_ids_json ?? []),
                    static fn (int $mediaId) => $mediaId !== $eventMedia->id,
                ));

                if ($resultIds === ($request->result_photo_ids_json ?? [])) {
                    return;
                }

                $request->forceFill([
                    'result_photo_ids_json' => $resultIds === [] ? null : $resultIds,
                    'best_distance' => $resultIds === [] ? null : $request->best_distance,
                ])->save();

                $searchRequestsUpdated++;
            });

        return [
            'variants_deleted' => $variantsDeleted,
            'face_crops_deleted' => $faceCropsDeleted,
            'vectors_deleted' => $vectorsDeleted,
            'face_rows_deleted' => $faceRowsDeleted,
            'safety_evaluations_deleted' => $safetyEvaluationsDeleted,
            'vlm_evaluations_deleted' => $vlmEvaluationsDeleted,
            'search_requests_updated' => $searchRequestsUpdated,
            'original_deleted' => $originalDeleted,
        ];
    }
}
