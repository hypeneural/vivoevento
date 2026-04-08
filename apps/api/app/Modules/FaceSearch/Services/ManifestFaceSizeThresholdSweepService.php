<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class ManifestFaceSizeThresholdSweepService
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
    ) {}

    /**
     * @param array<int, int|string> $thresholds
     * @param array<int, string> $splits
     * @param array<int, string> $occlusionBuckets
     * @param array<int, string> $faceSizeBuckets
     * @param array<int, string> $densityBuckets
     * @return array<string, mixed>
     */
    public function run(
        string $manifestPath,
        array $thresholds = [16, 24, 32, 40, 48, 64],
        int $limit = 0,
        string $selection = 'sequential',
        array $splits = [],
        array $occlusionBuckets = [],
        array $faceSizeBuckets = [],
        array $densityBuckets = [],
        string $providerKey = 'compreface',
        float $iouThreshold = 0.20,
        bool $includeInvalidAnnotations = false,
    ): array {
        $manifest = $this->loadManifest($manifestPath);
        $normalizedSplits = $this->normalizeSplits($splits);
        $normalizedThresholds = $this->normalizeThresholds($thresholds);
        $normalizedSelection = $this->normalizeSelection($selection);
        $normalizedOcclusionBuckets = $this->normalizeBucketFilter($occlusionBuckets, 'occlusion');
        $normalizedFaceSizeBuckets = $this->normalizeBucketFilter($faceSizeBuckets, 'face_size');
        $normalizedDensityBuckets = $this->normalizeBucketFilter($densityBuckets, 'density');

        if ($normalizedThresholds === []) {
            throw new RuntimeException('Manifest face-size sweep requires at least one valid min_face_size_px threshold.');
        }

        $entries = $this->selectEntries(
            entries: $this->filterEntries(
                entries: $this->resolveEntries($manifest, $normalizedSplits, $includeInvalidAnnotations),
                occlusionBuckets: $normalizedOcclusionBuckets,
                faceSizeBuckets: $normalizedFaceSizeBuckets,
                densityBuckets: $normalizedDensityBuckets,
            ),
            selection: $normalizedSelection,
            limit: max(0, $limit),
        );
        $settings = $this->makeSettings($providerKey, min($normalizedThresholds));
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = $this->detectEntry($entry, $settings);
        }

        $thresholdBreakdown = collect($normalizedThresholds)
            ->map(fn (int $threshold): array => $this->buildThresholdMetrics($rows, $threshold, $iouThreshold))
            ->values()
            ->all();

        return [
            'provider_key' => $providerKey,
            'manifest_path' => (string) $manifest['_resolved_path'],
            'dataset' => (string) ($manifest['dataset'] ?? 'unknown'),
            'lane' => (string) ($manifest['lane'] ?? 'unknown'),
            'selection' => $normalizedSelection,
            'splits' => $normalizedSplits,
            'filters' => [
                'occlusion_buckets' => $normalizedOcclusionBuckets,
                'face_size_buckets' => $normalizedFaceSizeBuckets,
                'density_buckets' => $normalizedDensityBuckets,
            ],
            'sample_size' => count($rows),
            'thresholds' => $normalizedThresholds,
            'iou_threshold' => round(max(0.0, min(1.0, $iouThreshold)), 4),
            'include_invalid_annotations' => $includeInvalidAnnotations,
            'baseline_summary' => $this->buildSummary($rows, null, $iouThreshold),
            'threshold_breakdown' => $thresholdBreakdown,
            'recommended_threshold' => $this->recommendThreshold($thresholdBreakdown),
            'sample_images' => $this->buildSampleRows($rows, $normalizedThresholds, $iouThreshold),
            'request_outcome' => collect($rows)->contains(fn (array $row): bool => $row['status'] !== 'success')
                ? 'degraded'
                : 'success',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $manifestPath): array
    {
        $resolvedPath = File::exists($manifestPath)
            ? $manifestPath
            : base_path(ltrim($manifestPath, '\\/'));

        if (! File::exists($resolvedPath)) {
            throw new RuntimeException(sprintf('Detection dataset manifest [%s] does not exist.', $manifestPath));
        }

        $payload = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! isset($payload['entries']) || ! is_array($payload['entries'])) {
            throw new RuntimeException('Detection dataset manifest is invalid.');
        }

        $payload['_resolved_path'] = $resolvedPath;

        return $payload;
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<int, string> $splits
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveEntries(array $manifest, array $splits, bool $includeInvalidAnnotations): Collection
    {
        $manifestDirectory = dirname((string) $manifest['_resolved_path']);
        $resolved = [];

        foreach ((array) $manifest['entries'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $split = strtolower((string) ($entry['split'] ?? 'unknown'));

            if ($splits !== [] && ! in_array($split, $splits, true)) {
                continue;
            }

            $annotations = $this->normalizeAnnotations($entry, $includeInvalidAnnotations);
            $absolutePath = $this->resolveImagePath($entry, $manifestDirectory);
            $faceSpans = collect($annotations)->pluck('face_span_min_px')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values()->all();
            $occlusionRates = collect($annotations)->pluck('occlusion_rate')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values()->all();

            $resolved[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'dataset' => (string) ($entry['dataset'] ?? ($manifest['dataset'] ?? 'unknown')),
                'split' => $split,
                'relative_path' => (string) ($entry['relative_path'] ?? ''),
                'absolute_path' => $absolutePath,
                'scene_type' => (string) ($entry['scene_type'] ?? 'unknown'),
                'quality_label' => (string) ($entry['quality_label'] ?? 'unknown'),
                'occlusion_rate' => is_numeric($entry['occlusion_rate'] ?? null)
                    ? (float) $entry['occlusion_rate']
                    : ($occlusionRates !== [] ? round((float) collect($occlusionRates)->avg(), 4) : null),
                'annotation_count' => count($annotations),
                'annotations' => $annotations,
                'face_span_min_px_min' => $this->percentile($faceSpans, 0),
                'face_span_min_px_p50' => $this->percentile($faceSpans, 50),
                'face_span_min_px_p95' => $this->percentile($faceSpans, 95),
                'face_span_min_px_max' => $this->percentile($faceSpans, 100),
                'invalid_annotation_count' => count(array_filter($annotations, fn (array $annotation): bool => (bool) ($annotation['invalid'] ?? false))),
            ];
        }

        return collect($resolved);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAnnotations(array $entry, bool $includeInvalidAnnotations): array
    {
        $rawAnnotations = [];

        if (isset($entry['annotations']) && is_array($entry['annotations'])) {
            $rawAnnotations = $entry['annotations'];
        } elseif (isset($entry['bbox']) && is_array($entry['bbox'])) {
            $rawAnnotations = [[
                'bbox' => $entry['bbox'],
                'face_span_min_px' => $entry['face_span_min_px'] ?? null,
                'occlusion_rate' => $entry['occlusion_rate'] ?? null,
                'invalid' => false,
            ]];
        }

        $normalized = [];

        foreach ($rawAnnotations as $index => $annotation) {
            if (! is_array($annotation)) {
                continue;
            }

            $bbox = $annotation['bbox'] ?? $annotation;

            if (! is_array($bbox)) {
                continue;
            }

            $x = $this->numeric($bbox['x'] ?? null);
            $y = $this->numeric($bbox['y'] ?? null);
            $width = $this->numeric($bbox['width'] ?? null);
            $height = $this->numeric($bbox['height'] ?? null);
            $invalid = (bool) ($annotation['invalid'] ?? false);

            if ($x === null || $y === null || $width === null || $height === null || $width <= 0 || $height <= 0) {
                continue;
            }

            if ($invalid && ! $includeInvalidAnnotations) {
                continue;
            }

            $normalized[] = [
                'index' => $index,
                'bbox' => [
                    'x' => (int) round($x),
                    'y' => (int) round($y),
                    'width' => (int) round($width),
                    'height' => (int) round($height),
                ],
                'face_span_min_px' => is_numeric($annotation['face_span_min_px'] ?? null)
                    ? round((float) $annotation['face_span_min_px'], 2)
                    : round((float) min($width, $height), 2),
                'occlusion_rate' => is_numeric($annotation['occlusion_rate'] ?? null)
                    ? round((float) $annotation['occlusion_rate'], 4)
                    : null,
                'invalid' => $invalid,
                'blur' => $annotation['blur'] ?? null,
                'illumination' => $annotation['illumination'] ?? null,
                'pose' => $annotation['pose'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function resolveImagePath(array $entry, string $manifestDirectory): string
    {
        $absolutePath = (string) ($entry['absolute_path'] ?? '');

        if ($absolutePath !== '' && File::exists($absolutePath)) {
            return $absolutePath;
        }

        $relativePath = (string) ($entry['relative_path'] ?? '');

        if ($relativePath === '') {
            return '';
        }

        return $manifestDirectory . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    private function normalizeSelection(string $selection): string
    {
        $selection = strtolower(trim($selection));

        return in_array($selection, ['sequential', 'highest_occlusion', 'smallest_face', 'dense_annotations'], true)
            ? $selection
            : 'sequential';
    }

    /**
     * @param array<int, int|string> $thresholds
     * @return array<int, int>
     */
    private function normalizeThresholds(array $thresholds): array
    {
        return collect($thresholds)
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
    }

    /**
     * @param array<int, string> $buckets
     * @return array<int, string>
     */
    private function normalizeBucketFilter(array $buckets, string $dimension): array
    {
        $allowed = match ($dimension) {
            'occlusion' => ['none', 'light', 'moderate', 'heavy', 'unknown'],
            'face_size' => ['small_lt_32', 'medium_32_63', 'large_64_95', 'xlarge_gte_96', 'unknown'],
            'density' => ['single', 'group_2_5', 'dense_6_10', 'crowd_11_plus'],
            default => [],
        };

        return collect($buckets)
            ->map(function (mixed $bucket): array {
                if (is_string($bucket)) {
                    return explode(',', $bucket);
                }

                return [(string) $bucket];
            })
            ->flatten()
            ->map(fn (mixed $bucket): string => strtolower(trim((string) $bucket)))
            ->filter(fn (string $bucket): bool => in_array($bucket, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $splits
     * @return array<int, string>
     */
    private function normalizeSplits(array $splits): array
    {
        return collect($splits)
            ->map(function (mixed $split): array {
                if (is_string($split)) {
                    return explode(',', $split);
                }

                return [(string) $split];
            })
            ->flatten()
            ->map(fn (mixed $split): string => strtolower(trim((string) $split)))
            ->filter()
            ->flatMap(fn (string $split): array => $split === 'all' ? ['train', 'validation', 'test'] : [$split])
            ->filter(fn (string $split): bool => in_array($split, ['train', 'validation', 'test'], true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $entries
     * @param array<int, string> $occlusionBuckets
     * @param array<int, string> $faceSizeBuckets
     * @param array<int, string> $densityBuckets
     * @return Collection<int, array<string, mixed>>
     */
    private function filterEntries(
        Collection $entries,
        array $occlusionBuckets,
        array $faceSizeBuckets,
        array $densityBuckets,
    ): Collection {
        return $entries
            ->filter(function (array $entry) use ($occlusionBuckets, $faceSizeBuckets, $densityBuckets): bool {
                if ($occlusionBuckets !== [] && ! in_array($this->occlusionBucket($entry), $occlusionBuckets, true)) {
                    return false;
                }

                if ($faceSizeBuckets !== [] && ! in_array($this->faceSizeBucket($entry), $faceSizeBuckets, true)) {
                    return false;
                }

                if ($densityBuckets !== [] && ! in_array($this->densityBucket($entry), $densityBuckets, true)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function selectEntries(Collection $entries, string $selection, int $limit): array
    {
        $sorted = match ($selection) {
            'highest_occlusion' => $entries
                ->sort(function (array $left, array $right): int {
                    $occlusion = ((float) ($right['occlusion_rate'] ?? -1.0)) <=> ((float) ($left['occlusion_rate'] ?? -1.0));

                    if ($occlusion !== 0) {
                        return $occlusion;
                    }

                    $size = ((float) ($left['face_span_min_px_min'] ?? INF)) <=> ((float) ($right['face_span_min_px_min'] ?? INF));

                    if ($size !== 0) {
                        return $size;
                    }

                    return strcmp((string) $left['id'], (string) $right['id']);
                })
                ->values(),
            'smallest_face' => $entries
                ->sort(function (array $left, array $right): int {
                    $size = ((float) ($left['face_span_min_px_min'] ?? INF)) <=> ((float) ($right['face_span_min_px_min'] ?? INF));

                    if ($size !== 0) {
                        return $size;
                    }

                    $density = ((int) $right['annotation_count']) <=> ((int) $left['annotation_count']);

                    if ($density !== 0) {
                        return $density;
                    }

                    return strcmp((string) $left['id'], (string) $right['id']);
                })
                ->values(),
            'dense_annotations' => $entries
                ->sort(function (array $left, array $right): int {
                    $density = ((int) $right['annotation_count']) <=> ((int) $left['annotation_count']);

                    if ($density !== 0) {
                        return $density;
                    }

                    $size = ((float) ($left['face_span_min_px_min'] ?? INF)) <=> ((float) ($right['face_span_min_px_min'] ?? INF));

                    if ($size !== 0) {
                        return $size;
                    }

                    return strcmp((string) $left['id'], (string) $right['id']);
                })
                ->values(),
            default => $entries->values(),
        };

        return ($limit > 0 ? $sorted->take($limit) : $sorted)->all();
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
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function detectEntry(array $entry, EventFaceSearchSetting $settings): array
    {
        $path = (string) ($entry['absolute_path'] ?? '');
        $reportEntry = $this->reportEntry($entry);

        if ($path === '' || ! File::exists($path)) {
            return [
                ...$reportEntry,
                'status' => 'missing',
                'latency_ms' => null,
                'detected_faces' => [],
                'detected_face_sides_px' => [],
                'error' => 'image_not_found',
            ];
        }

        $binary = (string) File::get($path);

        if ($binary === '') {
            return [
                ...$reportEntry,
                'status' => 'failed',
                'latency_ms' => null,
                'detected_faces' => [],
                'detected_face_sides_px' => [],
                'error' => 'empty_image_binary',
            ];
        }

        $media = new EventMedia([
            'media_type' => 'image',
            'mime_type' => File::mimeType($path) ?: 'image/png',
            'original_filename' => basename($path),
            'source_type' => 'manifest-face-size-sweep',
        ]);

        try {
            $startedAt = microtime(true);
            $faces = array_values($this->detector->detect($media, $settings, $binary));
            $latencyMs = round((microtime(true) - $startedAt) * 1000, 2);
            $faceSides = collect($faces)
                ->map(fn (DetectedFaceData $face): float => (float) min($face->boundingBox->width, $face->boundingBox->height))
                ->sort()
                ->values()
                ->all();

            return [
                ...$reportEntry,
                'status' => 'success',
                'latency_ms' => $latencyMs,
                'detected_faces' => $faces,
                'detected_face_sides_px' => array_map(
                    static fn (float $value): float => round($value, 2),
                    $faceSides,
                ),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                ...$reportEntry,
                'status' => 'failed',
                'latency_ms' => null,
                'detected_faces' => [],
                'detected_face_sides_px' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function reportEntry(array $entry): array
    {
        return [
            'id' => (string) ($entry['id'] ?? ''),
            'dataset' => (string) ($entry['dataset'] ?? 'unknown'),
            'split' => (string) ($entry['split'] ?? 'unknown'),
            'relative_path' => (string) ($entry['relative_path'] ?? ''),
            'absolute_path' => (string) ($entry['absolute_path'] ?? ''),
            'scene_type' => (string) ($entry['scene_type'] ?? 'unknown'),
            'quality_label' => (string) ($entry['quality_label'] ?? 'unknown'),
            'occlusion_rate' => $entry['occlusion_rate'] ?? null,
            'annotation_count' => (int) ($entry['annotation_count'] ?? 0),
            'annotations' => array_values($entry['annotations'] ?? []),
            'face_span_min_px_min' => $entry['face_span_min_px_min'] ?? null,
            'face_span_min_px_p50' => $entry['face_span_min_px_p50'] ?? null,
            'face_span_min_px_p95' => $entry['face_span_min_px_p95'] ?? null,
            'face_span_min_px_max' => $entry['face_span_min_px_max'] ?? null,
            'invalid_annotation_count' => (int) ($entry['invalid_annotation_count'] ?? 0),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildSummary(array $rows, ?int $threshold, float $iouThreshold): array
    {
        $successful = collect($rows)->where('status', 'success')->values();
        $latencies = $successful->pluck('latency_ms')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values()->all();
        $imageTotal = count($rows);
        $successfulImages = $successful->count();
        $annotationTotal = (int) collect($rows)->sum('annotation_count');
        $baselineDetectedTotal = (int) $successful->sum(fn (array $row): int => count($row['detected_faces']));
        $retainedDetectedTotal = 0;
        $retainedMatchedAnnotationTotal = 0;
        $retainedMatchedDetectionTotal = 0;
        $retainedSuccessfulImages = 0;

        foreach ($rows as $row) {
            if ($row['status'] !== 'success') {
                continue;
            }

            $metrics = $this->thresholdMetricsForRow($row, $threshold, $iouThreshold);
            $retainedDetectedTotal += (int) $metrics['detected_faces_count'];
            $retainedMatchedAnnotationTotal += (int) $metrics['matched_annotations_count'];
            $retainedMatchedDetectionTotal += (int) $metrics['matched_detections_count'];

            if ((int) $metrics['detected_faces_count'] > 0) {
                $retainedSuccessfulImages++;
            }
        }

        $recall = $annotationTotal > 0 ? round($retainedMatchedAnnotationTotal / $annotationTotal, 4) : null;
        $precision = $retainedDetectedTotal > 0 ? round($retainedMatchedDetectionTotal / $retainedDetectedTotal, 4) : null;

        return [
            'threshold' => $threshold,
            'images_sampled' => $imageTotal,
            'images_with_successful_detection' => $successfulImages,
            'images_failed_or_missing' => $imageTotal - $successfulImages,
            'annotated_faces_total' => $annotationTotal,
            'retained_detected_faces_total' => $retainedDetectedTotal,
            'retained_matched_annotations_total' => $retainedMatchedAnnotationTotal,
            'retained_matched_detections_total' => $retainedMatchedDetectionTotal,
            'annotation_recall_estimated' => $recall,
            'detection_precision_estimated' => $precision,
            'retained_detected_face_rate' => $baselineDetectedTotal > 0 ? round($retainedDetectedTotal / $baselineDetectedTotal, 4) : null,
            'retained_detected_image_rate' => $successfulImages > 0 ? round($retainedSuccessfulImages / $successfulImages, 4) : null,
            'p50_detect_latency_ms' => $this->percentile($latencies, 50),
            'p95_detect_latency_ms' => $this->percentile($latencies, 95),
            'annotation_precision_balance_score' => $this->balanceScore($recall, $precision),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildThresholdMetrics(array $rows, int $threshold, float $iouThreshold): array
    {
        return [
            ...$this->buildSummary($rows, $threshold, $iouThreshold),
            'split_breakdown' => $this->buildBreakdown($rows, fn (array $row): string => (string) ($row['split'] ?? 'unknown'), $threshold, $iouThreshold),
            'occlusion_breakdown' => $this->buildBreakdown($rows, fn (array $row): string => $this->occlusionBucket($row), $threshold, $iouThreshold),
            'face_size_breakdown' => $this->buildBreakdown($rows, fn (array $row): string => $this->faceSizeBucket($row), $threshold, $iouThreshold),
            'density_breakdown' => $this->buildBreakdown($rows, fn (array $row): string => $this->densityBucket($row), $threshold, $iouThreshold),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildBreakdown(array $rows, callable $groupKey, int $threshold, float $iouThreshold): array
    {
        return collect($rows)
            ->groupBy(fn (array $row): string => $groupKey($row))
            ->map(function (Collection $group, string $key) use ($threshold, $iouThreshold): array {
                $annotationTotal = (int) $group->sum('annotation_count');
                $retainedDetectedTotal = 0;
                $retainedMatchedAnnotationTotal = 0;
                $retainedMatchedDetectionTotal = 0;
                $successfulImages = 0;
                $retainedSuccessfulImages = 0;
                $baselineDetectedTotal = 0;

                foreach ($group as $row) {
                    if ($row['status'] !== 'success') {
                        continue;
                    }

                    $successfulImages++;
                    $baselineDetectedTotal += count($row['detected_faces']);
                    $metrics = $this->thresholdMetricsForRow($row, $threshold, $iouThreshold);
                    $retainedDetectedTotal += (int) $metrics['detected_faces_count'];
                    $retainedMatchedAnnotationTotal += (int) $metrics['matched_annotations_count'];
                    $retainedMatchedDetectionTotal += (int) $metrics['matched_detections_count'];

                    if ((int) $metrics['detected_faces_count'] > 0) {
                        $retainedSuccessfulImages++;
                    }
                }

                return [
                    'bucket' => $key,
                    'images' => $group->count(),
                    'annotated_faces_total' => $annotationTotal,
                    'retained_detected_faces_total' => $retainedDetectedTotal,
                    'retained_matched_annotations_total' => $retainedMatchedAnnotationTotal,
                    'retained_matched_detections_total' => $retainedMatchedDetectionTotal,
                    'annotation_recall_estimated' => $annotationTotal > 0 ? round($retainedMatchedAnnotationTotal / $annotationTotal, 4) : null,
                    'detection_precision_estimated' => $retainedDetectedTotal > 0 ? round($retainedMatchedDetectionTotal / $retainedDetectedTotal, 4) : null,
                    'retained_detected_face_rate' => $baselineDetectedTotal > 0 ? round($retainedDetectedTotal / $baselineDetectedTotal, 4) : null,
                    'retained_detected_image_rate' => $successfulImages > 0 ? round($retainedSuccessfulImages / $successfulImages, 4) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, int> $thresholds
     * @return array<int, array<string, mixed>>
     */
    private function buildSampleRows(array $rows, array $thresholds, float $iouThreshold): array
    {
        return collect($rows)
            ->map(function (array $row) use ($thresholds, $iouThreshold): array {
                $baseline = $this->thresholdMetricsForRow($row, null, $iouThreshold);
                $sample = $row;
                unset($sample['detected_faces']);

                return [
                    ...$sample,
                    'baseline' => [
                        'detected_faces_count' => $baseline['detected_faces_count'],
                        'matched_annotations_count' => $baseline['matched_annotations_count'],
                        'matched_detections_count' => $baseline['matched_detections_count'],
                        'annotation_recall_estimated' => $baseline['annotation_recall_estimated'],
                        'detection_precision_estimated' => $baseline['detection_precision_estimated'],
                        'detected_face_min_side_px_min' => $baseline['detected_face_min_side_px_min'],
                        'detected_face_min_side_px_p50' => $baseline['detected_face_min_side_px_p50'],
                        'detected_face_min_side_px_p95' => $baseline['detected_face_min_side_px_p95'],
                        'detected_face_min_side_px_max' => $baseline['detected_face_min_side_px_max'],
                        'best_iou' => $baseline['best_iou'],
                    ],
                    'threshold_breakdown' => collect($thresholds)
                        ->map(function (int $threshold) use ($row, $iouThreshold): array {
                            $metrics = $this->thresholdMetricsForRow($row, $threshold, $iouThreshold);

                            return [
                                'threshold' => $threshold,
                                'detected_faces_count' => $metrics['detected_faces_count'],
                                'matched_annotations_count' => $metrics['matched_annotations_count'],
                                'matched_detections_count' => $metrics['matched_detections_count'],
                                'annotation_recall_estimated' => $metrics['annotation_recall_estimated'],
                                'detection_precision_estimated' => $metrics['detection_precision_estimated'],
                                'detected_face_min_side_px_min' => $metrics['detected_face_min_side_px_min'],
                                'detected_face_min_side_px_p50' => $metrics['detected_face_min_side_px_p50'],
                                'detected_face_min_side_px_p95' => $metrics['detected_face_min_side_px_p95'],
                                'detected_face_min_side_px_max' => $metrics['detected_face_min_side_px_max'],
                                'best_iou' => $metrics['best_iou'],
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *   detected_faces_count:int,
     *   matched_annotations_count:int,
     *   matched_detections_count:int,
     *   annotation_recall_estimated:float|null,
     *   detection_precision_estimated:float|null,
     *   detected_face_min_side_px_min:float|null,
     *   detected_face_min_side_px_p50:float|null,
     *   detected_face_min_side_px_p95:float|null,
     *   detected_face_min_side_px_max:float|null,
     *   best_iou:float|null
     * }
     */
    private function thresholdMetricsForRow(array $row, ?int $threshold, float $iouThreshold): array
    {
        if ($row['status'] !== 'success') {
            return [
                'detected_faces_count' => 0,
                'matched_annotations_count' => 0,
                'matched_detections_count' => 0,
                'annotation_recall_estimated' => (int) $row['annotation_count'] > 0 ? 0.0 : null,
                'detection_precision_estimated' => null,
                'detected_face_min_side_px_min' => null,
                'detected_face_min_side_px_p50' => null,
                'detected_face_min_side_px_p95' => null,
                'detected_face_min_side_px_max' => null,
                'best_iou' => null,
            ];
        }

        $filteredFaces = $this->filterFacesByThreshold($row['detected_faces'], $threshold);
        $faceSides = collect($filteredFaces)
            ->map(fn (DetectedFaceData $face): float => (float) min($face->boundingBox->width, $face->boundingBox->height))
            ->sort()
            ->values()
            ->all();
        $matches = $this->matchAnnotations((array) ($row['annotations'] ?? []), $filteredFaces, $iouThreshold);
        $annotationCount = (int) ($row['annotation_count'] ?? 0);
        $detectedCount = count($filteredFaces);
        $matchedAnnotations = count($matches['matched_annotation_indices']);
        $matchedDetections = count($matches['matched_detection_indices']);

        return [
            'detected_faces_count' => $detectedCount,
            'matched_annotations_count' => $matchedAnnotations,
            'matched_detections_count' => $matchedDetections,
            'annotation_recall_estimated' => $annotationCount > 0 ? round($matchedAnnotations / $annotationCount, 4) : null,
            'detection_precision_estimated' => $detectedCount > 0 ? round($matchedDetections / $detectedCount, 4) : null,
            'detected_face_min_side_px_min' => $this->percentile($faceSides, 0),
            'detected_face_min_side_px_p50' => $this->percentile($faceSides, 50),
            'detected_face_min_side_px_p95' => $this->percentile($faceSides, 95),
            'detected_face_min_side_px_max' => $this->percentile($faceSides, 100),
            'best_iou' => $matches['best_iou'],
        ];
    }

    /**
     * @param array<int, DetectedFaceData> $faces
     * @return array<int, DetectedFaceData>
     */
    private function filterFacesByThreshold(array $faces, ?int $threshold): array
    {
        if ($threshold === null) {
            return array_values($faces);
        }

        return array_values(array_filter(
            $faces,
            fn (DetectedFaceData $face): bool => min($face->boundingBox->width, $face->boundingBox->height) >= $threshold,
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $annotations
     * @param array<int, DetectedFaceData> $faces
     * @return array{
     *   matched_pairs: array<int, array{annotation_index:int, detection_index:int, iou:float}>,
     *   matched_annotation_indices: array<int, int>,
     *   matched_detection_indices: array<int, int>,
     *   best_iou: float|null
     * }
     */
    private function matchAnnotations(array $annotations, array $faces, float $iouThreshold): array
    {
        $candidates = [];

        foreach ($annotations as $annotationIndex => $annotation) {
            foreach ($faces as $detectionIndex => $face) {
                $iou = $this->iou((array) ($annotation['bbox'] ?? []), $face);

                if ($iou < $iouThreshold) {
                    continue;
                }

                $candidates[] = [
                    'annotation_index' => $annotationIndex,
                    'detection_index' => $detectionIndex,
                    'iou' => round($iou, 4),
                ];
            }
        }

        usort($candidates, fn (array $left, array $right): int => $right['iou'] <=> $left['iou']);

        $matchedAnnotations = [];
        $matchedDetections = [];
        $pairs = [];

        foreach ($candidates as $candidate) {
            if (isset($matchedAnnotations[$candidate['annotation_index']]) || isset($matchedDetections[$candidate['detection_index']])) {
                continue;
            }

            $matchedAnnotations[$candidate['annotation_index']] = $candidate['annotation_index'];
            $matchedDetections[$candidate['detection_index']] = $candidate['detection_index'];
            $pairs[] = $candidate;
        }

        return [
            'matched_pairs' => $pairs,
            'matched_annotation_indices' => array_values($matchedAnnotations),
            'matched_detection_indices' => array_values($matchedDetections),
            'best_iou' => $pairs === [] ? null : round((float) max(array_column($pairs, 'iou')), 4),
        ];
    }

    /**
     * @param array<string, mixed> $annotationBox
     */
    private function iou(array $annotationBox, DetectedFaceData $face): float
    {
        $ax1 = (float) ($annotationBox['x'] ?? 0.0);
        $ay1 = (float) ($annotationBox['y'] ?? 0.0);
        $ax2 = $ax1 + (float) ($annotationBox['width'] ?? 0.0);
        $ay2 = $ay1 + (float) ($annotationBox['height'] ?? 0.0);

        $dx1 = (float) $face->boundingBox->x;
        $dy1 = (float) $face->boundingBox->y;
        $dx2 = $dx1 + (float) $face->boundingBox->width;
        $dy2 = $dy1 + (float) $face->boundingBox->height;

        $intersectWidth = max(0.0, min($ax2, $dx2) - max($ax1, $dx1));
        $intersectHeight = max(0.0, min($ay2, $dy2) - max($ay1, $dy1));
        $intersection = $intersectWidth * $intersectHeight;

        if ($intersection <= 0.0) {
            return 0.0;
        }

        $annotationArea = max(0.0, ((float) ($annotationBox['width'] ?? 0.0)) * ((float) ($annotationBox['height'] ?? 0.0)));
        $detectionArea = max(0.0, ((float) $face->boundingBox->width) * ((float) $face->boundingBox->height));
        $union = $annotationArea + $detectionArea - $intersection;

        return $union > 0.0 ? $intersection / $union : 0.0;
    }

    /**
     * @param array<int, array<string, mixed>> $thresholdBreakdown
     * @return array<string, mixed>|null
     */
    private function recommendThreshold(array $thresholdBreakdown): ?array
    {
        $rows = collect($thresholdBreakdown)
            ->filter(fn (array $row): bool => is_numeric($row['annotation_precision_balance_score'] ?? null))
            ->sort(function (array $left, array $right): int {
                $comparisons = [
                    ((float) ($right['annotation_precision_balance_score'] ?? -1.0)) <=> ((float) ($left['annotation_precision_balance_score'] ?? -1.0)),
                    ((float) ($right['annotation_recall_estimated'] ?? -1.0)) <=> ((float) ($left['annotation_recall_estimated'] ?? -1.0)),
                    ((float) ($right['detection_precision_estimated'] ?? -1.0)) <=> ((float) ($left['detection_precision_estimated'] ?? -1.0)),
                    ((float) ($right['retained_detected_image_rate'] ?? -1.0)) <=> ((float) ($left['retained_detected_image_rate'] ?? -1.0)),
                    ((int) ($left['threshold'] ?? PHP_INT_MAX)) <=> ((int) ($right['threshold'] ?? PHP_INT_MAX)),
                ];

                foreach ($comparisons as $comparison) {
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $selected = (array) $rows->first();

        return [
            'threshold' => (int) ($selected['threshold'] ?? 0),
            'annotation_recall_estimated' => $selected['annotation_recall_estimated'] ?? null,
            'detection_precision_estimated' => $selected['detection_precision_estimated'] ?? null,
            'annotation_precision_balance_score' => $selected['annotation_precision_balance_score'] ?? null,
            'selection_rule' => 'max_harmonic_mean_of_annotation_recall_and_precision_then_max_recall_then_max_precision_then_max_retained_image_rate_then_min_threshold',
        ];
    }

    private function balanceScore(?float $recall, ?float $precision): ?float
    {
        if ($recall === null || $precision === null || $recall <= 0.0 || $precision <= 0.0) {
            return $recall === 0.0 || $precision === 0.0 ? 0.0 : null;
        }

        return round((2 * $recall * $precision) / ($recall + $precision), 4);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function occlusionBucket(array $row): string
    {
        $value = $this->numeric($row['occlusion_rate'] ?? null);

        if ($value === null) {
            return 'unknown';
        }

        return match (true) {
            $value <= 0.0 => 'none',
            $value <= 0.20 => 'light',
            $value <= 0.40 => 'moderate',
            default => 'heavy',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function faceSizeBucket(array $row): string
    {
        $value = $this->numeric($row['face_span_min_px_min'] ?? null);

        if ($value === null) {
            return 'unknown';
        }

        return match (true) {
            $value < 32.0 => 'small_lt_32',
            $value < 64.0 => 'medium_32_63',
            $value < 96.0 => 'large_64_95',
            default => 'xlarge_gte_96',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function densityBucket(array $row): string
    {
        $count = (int) ($row['annotation_count'] ?? 0);

        return match (true) {
            $count <= 1 => 'single',
            $count <= 5 => 'group_2_5',
            $count <= 10 => 'dense_6_10',
            default => 'crowd_11_plus',
        };
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
        $percentile = max(0, min(100, $percentile));
        $position = ($percentile / 100) * (count($values) - 1);
        $lowerIndex = (int) floor($position);
        $upperIndex = (int) ceil($position);
        $lower = $values[$lowerIndex];
        $upper = $values[$upperIndex];

        if ($lowerIndex === $upperIndex) {
            return round($lower, 2);
        }

        $weight = $position - $lowerIndex;

        return round($lower + (($upper - $lower) * $weight), 2);
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
