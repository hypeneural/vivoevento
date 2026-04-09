<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\FaceSearch\Services\FaceSearchRouter;
use App\Modules\MediaProcessing\Models\EventMedia;

class IndexMediaFacesAction
{
    public function __construct(
        private readonly FaceSearchRouter $router,
    ) {}

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

        return $this->router->backendForSettings($settings)->indexMedia($media, $settings);
    }
}
