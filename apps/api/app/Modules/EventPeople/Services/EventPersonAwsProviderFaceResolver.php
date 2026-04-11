<?php

namespace App\Modules\EventPeople\Services;

use App\Modules\EventPeople\Models\EventPersonRepresentativeFace;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;
use Illuminate\Support\Collection;

class EventPersonAwsProviderFaceResolver
{
    /**
     * @param  Collection<int, EventPersonRepresentativeFace>  $representatives
     * @return array<int, string>
     */
    public function resolveFaceIds(int $eventId, EventFaceSearchSetting $settings, Collection $representatives): array
    {
        $collectionId = is_string($settings->aws_collection_id) && trim($settings->aws_collection_id) !== ''
            ? trim($settings->aws_collection_id)
            : "eventovivo-face-search-event-{$eventId}";

        $faces = $representatives
            ->map(fn (EventPersonRepresentativeFace $representative) => $representative->face)
            ->filter();

        $providerRecordsByMedia = FaceSearchProviderRecord::query()
            ->where('event_id', $eventId)
            ->where('backend_key', 'aws_rekognition')
            ->where('collection_id', $collectionId)
            ->where('searchable', true)
            ->whereNotNull('face_id')
            ->whereIn('event_media_id', $faces->pluck('event_media_id')->unique()->all())
            ->get()
            ->groupBy('event_media_id');

        $resolved = [];

        foreach ($representatives as $representative) {
            $face = $representative->face;

            if (! $face) {
                continue;
            }

            /** @var Collection<int, FaceSearchProviderRecord> $records */
            $records = $providerRecordsByMedia->get($face->event_media_id, collect());

            if ($records->isEmpty()) {
                continue;
            }

            $match = $records
                ->map(fn (FaceSearchProviderRecord $record): array => [
                    'record' => $record,
                    'iou' => $this->intersectionOverUnion(
                        [
                            'x' => (float) $face->bbox_x,
                            'y' => (float) $face->bbox_y,
                            'width' => (float) $face->bbox_w,
                            'height' => (float) $face->bbox_h,
                        ],
                        $this->providerBoundingBox($record),
                    ),
                ])
                ->sortByDesc('iou')
                ->first();

            if (! is_array($match)) {
                continue;
            }

            /** @var FaceSearchProviderRecord $record */
            $record = $match['record'];

            if ((float) $match['iou'] <= 0.05 || ! is_string($record->face_id) || $record->face_id === '') {
                continue;
            }

            $resolved[(int) $face->id] = $record->face_id;
        }

        return $resolved;
    }

    /**
     * @return array{x:float,y:float,width:float,height:float}
     */
    private function providerBoundingBox(FaceSearchProviderRecord $record): array
    {
        $bbox = is_array($record->bbox_json) ? $record->bbox_json : [];

        return [
            'x' => (float) ($bbox['x'] ?? 0),
            'y' => (float) ($bbox['y'] ?? 0),
            'width' => (float) ($bbox['width'] ?? 0),
            'height' => (float) ($bbox['height'] ?? 0),
        ];
    }

    /**
     * @param  array{x:float,y:float,width:float,height:float}  $left
     * @param  array{x:float,y:float,width:float,height:float}  $right
     */
    private function intersectionOverUnion(array $left, array $right): float
    {
        if ($right['width'] <= 0 || $right['height'] <= 0) {
            return 0.0;
        }

        $leftX2 = $left['x'] + $left['width'];
        $leftY2 = $left['y'] + $left['height'];
        $rightX2 = $right['x'] + $right['width'];
        $rightY2 = $right['y'] + $right['height'];

        $intersectionWidth = max(0.0, min($leftX2, $rightX2) - max($left['x'], $right['x']));
        $intersectionHeight = max(0.0, min($leftY2, $rightY2) - max($left['y'], $right['y']));
        $intersectionArea = $intersectionWidth * $intersectionHeight;

        if ($intersectionArea <= 0.0) {
            return 0.0;
        }

        $leftArea = max(1.0, $left['width'] * $left['height']);
        $rightArea = max(1.0, $right['width'] * $right['height']);
        $unionArea = max(1.0, $leftArea + $rightArea - $intersectionArea);

        return $intersectionArea / $unionArea;
    }
}
