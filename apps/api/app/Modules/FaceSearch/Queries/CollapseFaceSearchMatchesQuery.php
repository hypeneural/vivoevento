<?php

namespace App\Modules\FaceSearch\Queries;

use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;

class CollapseFaceSearchMatchesQuery
{
    /**
     * @param array<int, FaceSearchMatchData> $matches
     * @return array<int, array{
     *   event_media_id:int,
     *   best_distance:float,
     *   best_quality_tier:string|null,
     *   best_quality_score:float|null,
     *   best_face_area_ratio:float|null,
     *   matched_face_ids:array<int, int>
     * }>
     */
    public function execute(array $matches): array
    {
        $collapsed = [];

        foreach ($matches as $match) {
            $current = $collapsed[$match->eventMediaId] ?? [
                'event_media_id' => $match->eventMediaId,
                'best_distance' => $match->distance,
                'best_quality_tier' => $match->qualityTier,
                'best_quality_score' => $match->qualityScore,
                'best_face_area_ratio' => $match->faceAreaRatio,
                'matched_face_ids' => [],
            ];

            $current['best_distance'] = min($current['best_distance'], $match->distance);
            $current['best_quality_tier'] = $this->bestQualityTier(
                $current['best_quality_tier'],
                $match->qualityTier,
            );
            $current['best_quality_score'] = $this->maxNullable(
                $current['best_quality_score'],
                $match->qualityScore,
            );
            $current['best_face_area_ratio'] = $this->maxNullable(
                $current['best_face_area_ratio'],
                $match->faceAreaRatio,
            );
            $current['matched_face_ids'][] = $match->faceId;

            $collapsed[$match->eventMediaId] = $current;
        }

        $results = array_values($collapsed);

        usort($results, function (array $left, array $right): int {
            $distance = $left['best_distance'] <=> $right['best_distance'];

            if ($distance !== 0) {
                return $distance;
            }

            $tier = \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($right['best_quality_tier'] ?? null)
                <=> \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($left['best_quality_tier'] ?? null);

            if ($tier !== 0) {
                return $tier;
            }

            $quality = ($right['best_quality_score'] ?? -1.0) <=> ($left['best_quality_score'] ?? -1.0);

            if ($quality !== 0) {
                return $quality;
            }

            return ($right['best_face_area_ratio'] ?? -1.0) <=> ($left['best_face_area_ratio'] ?? -1.0);
        });

        return $results;
    }

    private function maxNullable(?float $left, ?float $right): ?float
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        return max($left, $right);
    }

    private function bestQualityTier(?string $left, ?string $right): ?string
    {
        return \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($right)
            > \App\Modules\FaceSearch\Enums\FaceQualityTier::rankFor($left)
            ? $right
            : $left;
    }
}
