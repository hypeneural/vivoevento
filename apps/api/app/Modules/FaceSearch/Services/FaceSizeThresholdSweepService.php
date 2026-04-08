<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class FaceSizeThresholdSweepService
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
    ) {}

    /**
     * @param array<int, int|string> $thresholds
     * @return array<string, mixed>
     */
    public function run(
        string $datasetRoot,
        string $groundTruthPath,
        array $thresholds = [16, 24, 32, 40, 48, 64],
        int $limit = 25,
        string $selection = 'smallest_annotated_faces',
        string $providerKey = 'compreface',
    ): array {
        $resolvedDatasetRoot = $this->resolvePath($datasetRoot);
        $resolvedGroundTruthPath = $this->resolvePath($groundTruthPath);
        $thresholds = $this->normalizeThresholds($thresholds);

        if (! is_dir($resolvedDatasetRoot)) {
            throw new RuntimeException(sprintf('Face-size sweep dataset root [%s] does not exist.', $datasetRoot));
        }

        if (! File::exists($resolvedGroundTruthPath)) {
            throw new RuntimeException(sprintf('Face-size sweep ground truth [%s] does not exist.', $groundTruthPath));
        }

        if ($thresholds === []) {
            throw new RuntimeException('Face-size sweep requires at least one valid min_face_size_px threshold.');
        }

        $limit = max(1, $limit);
        $selection = $this->normalizeSelection($selection);
        $catalog = $this->loadGroundTruthCatalog($resolvedGroundTruthPath);
        $selectedImages = $this->selectImages($catalog, $selection, $limit);
        $settings = $this->makeSettings($providerKey, min($thresholds));
        $rows = [];

        foreach ($selectedImages as $image) {
            $rows[] = $this->probeImage($resolvedDatasetRoot, $image, $settings);
        }

        return [
            'provider_key' => $providerKey,
            'dataset_root' => $resolvedDatasetRoot,
            'ground_truth_path' => $resolvedGroundTruthPath,
            'selection' => $selection,
            'sample_size' => count($rows),
            'thresholds' => $thresholds,
            'summary' => $this->buildSummary($rows),
            'threshold_breakdown' => $this->buildThresholdBreakdown($rows, $thresholds),
            'sample_images' => $rows,
            'smallest_detected_faces' => $this->smallestDetectedFaces($rows),
            'request_outcome' => collect($rows)->contains(fn (array $row): bool => $row['status'] !== 'success')
                ? 'degraded'
                : 'success',
        ];
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (File::exists($path) || is_dir($path)) {
            return $path;
        }

        $userProfile = getenv('USERPROFILE') ?: '';

        return str_replace('%USERPROFILE%', (string) $userProfile, $path);
    }

    /**
     * @param array<int, int|string> $thresholds
     * @return array<int, int>
     */
    private function normalizeThresholds(array $thresholds): array
    {
        $values = collect($thresholds)
            ->map(function (mixed $threshold): array {
                if (is_string($threshold)) {
                    return explode(',', $threshold);
                }

                return [$threshold];
            })
            ->flatten()
            ->map(fn (mixed $threshold): int => (int) trim((string) $threshold))
            ->filter(fn (int $threshold): bool => $threshold > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $values;
    }

    private function normalizeSelection(string $selection): string
    {
        $selection = strtolower(trim($selection));

        return in_array($selection, ['smallest_annotated_faces', 'multi_face_density', 'sequential'], true)
            ? $selection
            : 'smallest_annotated_faces';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadGroundTruthCatalog(string $groundTruthPath): Collection
    {
        $grouped = [];
        $lines = preg_split('/\r\n|\r|\n/', (string) File::get($groundTruthPath)) ?: [];

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (count($parts) !== 9) {
                continue;
            }

            [$imageName, $leftEyeX, $leftEyeY, $rightEyeX, $rightEyeY, $noseX, $noseY, $mouthX, $mouthY] = $parts;
            $span = $this->annotatedFaceSpan([
                [(float) $leftEyeX, (float) $leftEyeY],
                [(float) $rightEyeX, (float) $rightEyeY],
                [(float) $noseX, (float) $noseY],
                [(float) $mouthX, (float) $mouthY],
            ]);

            $grouped[$imageName][] = $span;
        }

        return collect($grouped)
            ->map(function (array $spans, string $imageName): array {
                sort($spans);

                return [
                    'image_name' => $imageName,
                    'annotated_faces_count' => count($spans),
                    'estimated_annotated_face_span_min_px' => round((float) ($spans[0] ?? 0.0), 2),
                    'estimated_annotated_face_span_p50_px' => $this->percentile($spans, 50),
                    'estimated_annotated_face_span_p95_px' => $this->percentile($spans, 95),
                    'estimated_annotated_face_span_max_px' => round((float) ($spans[count($spans) - 1] ?? 0.0), 2),
                    'annotated_face_spans_px' => array_map(
                        static fn (float $value): float => round($value, 2),
                        array_values($spans),
                    ),
                ];
            })
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $catalog
     * @return array<int, array<string, mixed>>
     */
    private function selectImages(Collection $catalog, string $selection, int $limit): array
    {
        $selected = match ($selection) {
            'multi_face_density' => $catalog
                ->sort(function (array $left, array $right): int {
                    $faces = ((int) $right['annotated_faces_count']) <=> ((int) $left['annotated_faces_count']);

                    if ($faces !== 0) {
                        return $faces;
                    }

                    $span = ((float) $left['estimated_annotated_face_span_min_px']) <=> ((float) $right['estimated_annotated_face_span_min_px']);

                    if ($span !== 0) {
                        return $span;
                    }

                    return strcmp((string) $left['image_name'], (string) $right['image_name']);
                })
                ->values(),
            'sequential' => $catalog
                ->sortBy('image_name')
                ->values(),
            default => $catalog
                ->sort(function (array $left, array $right): int {
                    $span = ((float) $left['estimated_annotated_face_span_min_px']) <=> ((float) $right['estimated_annotated_face_span_min_px']);

                    if ($span !== 0) {
                        return $span;
                    }

                    $faces = ((int) $right['annotated_faces_count']) <=> ((int) $left['annotated_faces_count']);

                    if ($faces !== 0) {
                        return $faces;
                    }

                    return strcmp((string) $left['image_name'], (string) $right['image_name']);
                })
                ->values(),
        };

        return $selected->take($limit)->all();
    }

    private function makeSettings(string $providerKey, int $minFaceSizePx): EventFaceSearchSetting
    {
        return new EventFaceSearchSetting(array_merge(
            EventFaceSearchSetting::defaultAttributes(),
            [
                'enabled' => true,
                'provider_key' => $providerKey,
                'min_face_size_px' => $minFaceSizePx,
            ],
        ));
    }

    /**
     * @param array<string, mixed> $image
     * @return array<string, mixed>
     */
    private function probeImage(
        string $datasetRoot,
        array $image,
        EventFaceSearchSetting $settings,
    ): array {
        $path = $datasetRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $image['image_name']);

        if (! File::exists($path)) {
            return [
                ...$image,
                'image_path' => $path,
                'status' => 'missing',
                'latency_ms' => null,
                'detected_faces_count' => 0,
                'detected_face_min_side_px_min' => null,
                'detected_face_min_side_px_p50' => null,
                'detected_face_min_side_px_p95' => null,
                'detected_face_min_side_px_max' => null,
                'detected_face_sides_px' => [],
                'error' => 'image_not_found',
            ];
        }

        $binary = (string) File::get($path);

        if ($binary === '') {
            return [
                ...$image,
                'image_path' => $path,
                'status' => 'failed',
                'latency_ms' => null,
                'detected_faces_count' => 0,
                'detected_face_min_side_px_min' => null,
                'detected_face_min_side_px_p50' => null,
                'detected_face_min_side_px_p95' => null,
                'detected_face_min_side_px_max' => null,
                'detected_face_sides_px' => [],
                'error' => 'empty_image_binary',
            ];
        }

        $media = new EventMedia([
            'media_type' => 'image',
            'mime_type' => File::mimeType($path) ?: 'image/jpeg',
            'original_filename' => $image['image_name'],
            'source_type' => 'face-size-sweep',
        ]);

        try {
            $startedAt = microtime(true);
            $faces = array_values($this->detector->detect($media, $settings, $binary));
            $latencyMs = round((microtime(true) - $startedAt) * 1000, 2);
            $faceSides = collect($faces)
                ->map(fn ($face): float => (float) min($face->boundingBox->width, $face->boundingBox->height))
                ->sort()
                ->values()
                ->all();

            return [
                ...$image,
                'image_path' => $path,
                'status' => 'success',
                'latency_ms' => $latencyMs,
                'detected_faces_count' => count($faceSides),
                'detected_face_min_side_px_min' => $this->percentile($faceSides, 0),
                'detected_face_min_side_px_p50' => $this->percentile($faceSides, 50),
                'detected_face_min_side_px_p95' => $this->percentile($faceSides, 95),
                'detected_face_min_side_px_max' => $this->percentile($faceSides, 100),
                'detected_face_sides_px' => array_map(
                    static fn (float $value): float => round($value, 2),
                    $faceSides,
                ),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                ...$image,
                'image_path' => $path,
                'status' => 'failed',
                'latency_ms' => null,
                'detected_faces_count' => 0,
                'detected_face_min_side_px_min' => null,
                'detected_face_min_side_px_p50' => null,
                'detected_face_min_side_px_p95' => null,
                'detected_face_min_side_px_max' => null,
                'detected_face_sides_px' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int|float|null>
     */
    private function buildSummary(array $rows): array
    {
        $successful = collect($rows)->where('status', 'success')->values();
        $latencies = $successful->pluck('latency_ms')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->all();
        $annotatedFacesTotal = collect($rows)->sum(fn (array $row): int => (int) ($row['annotated_faces_count'] ?? 0));
        $detectedFacesTotal = $successful->sum(fn (array $row): int => (int) ($row['detected_faces_count'] ?? 0));

        return [
            'images_sampled' => count($rows),
            'images_with_successful_detection' => $successful->count(),
            'images_failed_or_missing' => count($rows) - $successful->count(),
            'annotated_faces_total' => $annotatedFacesTotal,
            'detected_faces_total' => $detectedFacesTotal,
            'annotated_to_detected_face_ratio' => $annotatedFacesTotal > 0
                ? round($detectedFacesTotal / $annotatedFacesTotal, 4)
                : null,
            'p50_detect_latency_ms' => $this->percentile($latencies, 50),
            'p95_detect_latency_ms' => $this->percentile($latencies, 95),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, int> $thresholds
     * @return array<int, array<string, int|float|null>>
     */
    private function buildThresholdBreakdown(array $rows, array $thresholds): array
    {
        $successful = collect($rows)->where('status', 'success')->values();
        $sampledImages = count($rows);
        $successfulImages = $successful->count();
        $detectedFacesTotal = max(1, (int) $successful->sum(fn (array $row): int => (int) ($row['detected_faces_count'] ?? 0)));
        $annotatedFacesTotal = max(1, (int) collect($rows)->sum(fn (array $row): int => (int) ($row['annotated_faces_count'] ?? 0)));

        return array_map(function (int $threshold) use ($rows, $successful, $sampledImages, $successfulImages, $detectedFacesTotal, $annotatedFacesTotal): array {
            $imagesWithAnyDetectedFace = $successful
                ->filter(fn (array $row): bool => collect((array) ($row['detected_face_sides_px'] ?? []))
                    ->contains(fn ($value): bool => is_numeric($value) && (float) $value >= $threshold))
                ->count();

            $detectedFacesAbove = $successful->sum(function (array $row) use ($threshold): int {
                return collect((array) ($row['detected_face_sides_px'] ?? []))
                    ->filter(fn ($value): bool => is_numeric($value) && (float) $value >= $threshold)
                    ->count();
            });

            $annotatedFacesAbove = collect($rows)->sum(function (array $row) use ($threshold): int {
                return collect((array) ($row['annotated_face_spans_px'] ?? []))
                    ->filter(fn ($value): bool => is_numeric($value) && (float) $value >= $threshold)
                    ->count();
            });

            $imagesWithAnyAnnotatedFace = collect($rows)
                ->filter(fn (array $row): bool => collect((array) ($row['annotated_face_spans_px'] ?? []))
                    ->contains(fn ($value): bool => is_numeric($value) && (float) $value >= $threshold))
                ->count();

            return [
                'threshold' => $threshold,
                'images_with_any_detected_face_gte_threshold' => $imagesWithAnyDetectedFace,
                'images_with_any_annotated_face_gte_threshold' => $imagesWithAnyAnnotatedFace,
                'detected_faces_gte_threshold' => $detectedFacesAbove,
                'annotated_faces_estimated_gte_threshold' => $annotatedFacesAbove,
                'retained_detected_face_rate' => round($detectedFacesAbove / $detectedFacesTotal, 4),
                'retained_detected_image_rate' => $successfulImages > 0
                    ? round($imagesWithAnyDetectedFace / $successfulImages, 4)
                    : null,
                'annotated_face_rate_estimated' => round($annotatedFacesAbove / $annotatedFacesTotal, 4),
                'annotated_image_rate_estimated' => $sampledImages > 0
                    ? round($imagesWithAnyAnnotatedFace / $sampledImages, 4)
                    : null,
            ];
        }, $thresholds);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function smallestDetectedFaces(array $rows): array
    {
        return collect($rows)
            ->where('status', 'success')
            ->filter(fn (array $row): bool => is_numeric($row['detected_face_min_side_px_min'] ?? null))
            ->sortBy(fn (array $row): float => (float) $row['detected_face_min_side_px_min'])
            ->take(10)
            ->values()
            ->map(fn (array $row): array => [
                'image_name' => $row['image_name'],
                'annotated_faces_count' => $row['annotated_faces_count'],
                'estimated_annotated_face_span_min_px' => $row['estimated_annotated_face_span_min_px'],
                'detected_faces_count' => $row['detected_faces_count'],
                'detected_face_min_side_px_min' => $row['detected_face_min_side_px_min'],
                'detected_face_min_side_px_p50' => $row['detected_face_min_side_px_p50'],
                'latency_ms' => $row['latency_ms'],
            ])
            ->all();
    }

    /**
     * @param array<int, array{0:float,1:float}> $points
     */
    private function annotatedFaceSpan(array $points): float
    {
        $xValues = array_map(static fn (array $point): float => $point[0], $points);
        $yValues = array_map(static fn (array $point): float => $point[1], $points);

        return min(max($xValues) - min($xValues), max($yValues) - min($yValues));
    }

    /**
     * @param array<int, float> $values
     */
    private function percentile(array $values, int $percentile): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $normalizedPercentile = max(0, min(100, $percentile));
        $position = (int) ceil(($normalizedPercentile / 100) * count($values)) - 1;
        $position = max(0, min($position, count($values) - 1));

        return round((float) $values[$position], 2);
    }
}
