<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\PerceptualHashService;

class SyncEventMediaDuplicateGroupAction
{
    private const HAMMING_DISTANCE_THRESHOLD = 6;

    public function __construct(
        private readonly PerceptualHashService $perceptualHashService,
    ) {}

    /**
     * @return array{
     *   status:string,
     *   perceptual_hash:string,
     *   duplicate_group_key:string|null,
     *   matched_media_id:int|null,
     *   hamming_distance:int|null,
     *   threshold:int,
     *   match_type:string|null
     * }
     */
    public function execute(EventMedia $media, string $perceptualHash): array
    {
        $bestMatch = $this->findBestMatch($media, $perceptualHash);
        $groupKey = null;
        $matchedMediaId = null;
        $distance = null;
        $matchType = null;

        if ($bestMatch !== null) {
            $groupKey = $bestMatch->duplicate_group_key ?: $this->makeGroupKey($bestMatch);
            $matchedMediaId = $bestMatch->id;
            $distance = $this->perceptualHashService->hammingDistance($perceptualHash, $bestMatch->perceptual_hash);
            $matchType = $distance === 0 ? 'exact' : 'similar';

            if ($bestMatch->duplicate_group_key !== $groupKey) {
                $bestMatch->forceFill([
                    'duplicate_group_key' => $groupKey,
                ])->saveQuietly();
            }
        }

        $media->forceFill([
            'perceptual_hash' => $perceptualHash,
            'duplicate_group_key' => $groupKey,
        ])->save();

        return [
            'status' => $matchedMediaId !== null ? 'grouped' : 'indexed',
            'perceptual_hash' => $perceptualHash,
            'duplicate_group_key' => $groupKey,
            'matched_media_id' => $matchedMediaId,
            'hamming_distance' => $distance,
            'threshold' => self::HAMMING_DISTANCE_THRESHOLD,
            'match_type' => $matchType,
        ];
    }

    private function findBestMatch(EventMedia $media, string $perceptualHash): ?EventMedia
    {
        $bestMatch = null;
        $bestDistance = null;

        $candidates = EventMedia::query()
            ->where('event_id', $media->event_id)
            ->where('media_type', 'image')
            ->whereKeyNot($media->id)
            ->whereNotNull('perceptual_hash')
            ->select(['id', 'event_id', 'perceptual_hash', 'duplicate_group_key'])
            ->get();

        foreach ($candidates as $candidate) {
            $distance = $this->perceptualHashService->hammingDistance($perceptualHash, $candidate->perceptual_hash);

            if ($distance === null || $distance > self::HAMMING_DISTANCE_THRESHOLD) {
                continue;
            }

            if ($bestDistance === null || $distance < $bestDistance || ($distance === $bestDistance && $candidate->id < $bestMatch?->id)) {
                $bestMatch = $candidate;
                $bestDistance = $distance;
            }
        }

        return $bestMatch;
    }

    private function makeGroupKey(EventMedia $anchor): string
    {
        return "dup_evt_{$anchor->event_id}_m_{$anchor->id}";
    }
}
