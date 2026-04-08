<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class DetectionDatasetProbeService
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
    ) {}

    /**
     * @param array<int, string> $splits
     * @return array<string, mixed>
     */
    public function run(
        string $manifestPath,
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
        $selection = $this->normalizeSelection($selection);
        $normalizedOcclusionBuckets = $this->normalizeBucketFilter($occlusionBuckets, 'occlusion');
        $normalizedFaceSizeBuckets = $this->normalizeBucketFilter($faceSizeBuckets, 'face_size');
        $normalizedDensityBuckets = $this->normalizeBucketFilter($densityBuckets, 'density');
        $entries = $this->selectEntries(
            entries: $this->filterEntries(
                entries: $this->resolveEntries($manifest, $normalizedSplits, $includeInvalidAnnotations),
                occlusionBuckets: $normalizedOcclusionBuckets,
                faceSizeBuckets: $normalizedFaceSizeBuckets,
                densityBuckets: $normalizedDensityBuckets,
            ),
            selection: $selection,
            limit: max(0, $limit),
        );
        $settings = $this->makeSettings($providerKey);
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = $this->probeEntry($entry, $settings, $iouThreshold);
        }

        return [
            'provider_key' => $providerKey,
            'manifest_path' => (string) $manifest['_resolved_path'],
            'dataset' => (string) ($manifest['dataset'] ?? 'unknown'),
            'lane' => (string) ($manifest['lane'] ?? 'unknown'),
            'selection' => $selection,
            'splits' => $normalizedSplits,
            'filters' => [
                'occlusion_buckets' => $normalizedOcclusionBuckets,
                'face_size_buckets' => $normalizedFaceSizeBuckets,
                'density_buckets' => $normalizedDensityBuckets,
            ],
            'sample_size' => count($rows),
            'iou_threshold' => round(max(0.0, min(1.0, $iouThreshold)), 4),
            'include_invalid_annotations' => $includeInvalidAnnotations,
            'summary' => $this->buildSummary($rows),
            'split_breakdown' => $this->buildBreakdown($rows, static fn (array $row): string => (string) ($row['split'] ?? 'unknown')),
            'occlusion_breakdown' => $this->buildBreakdown($rows, fn (array $row): string => $this->occlusionBucket($row)),
            'face_size_breakdown' => $this->buildBreakdown($rows, fn (array $row): string => $this->faceSizeBucket($row)),
            'density_breakdown' => $this->buildBreakdown($rows, fn (array $row): string => $this->densityBucket($row)),
            'sample_images' => $rows,
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

    private function makeSettings(string $providerKey): EventFaceSearchSetting
    {
        return new EventFaceSearchSetting(array_merge(
            EventFaceSearchSetting::defaultAttributes(),
            [
                'enabled' => true,
                'provider_key' => $providerKey,
            ],
        ));
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function probeEntry(array $entry, EventFaceSearchSetting $settings, float $iouThreshold): array
    {
        $path = (string) ($entry['absolute_path'] ?? '');
        $reportEntry = $this->reportEntry($entry);

        if ($path === '' || ! File::exists($path)) {
            return [
                ...$reportEntry,
                'status' => 'missing',
                'latency_ms' => null,
                'detected_faces_count' => 0,
                'matched_annotations_count' => 0,
                'matched_detections_count' => 0,
                'annotation_recall_estimated' => 0.0,
                'detection_precision_estimated' => null,
                'detected_face_min_side_px_min' => null,
                'detected_face_min_side_px_p50' => null,
                'detected_face_min_side_px_p95' => null,
                'detected_face_min_side_px_max' => null,
                'best_iou' => null,
                'error' => 'image_not_found',
            ];
        }

        $binary = (string) File::get($path);

        if ($binary === '') {
            return [
                ...$reportEntry,
                'status' => 'failed',
                'latency_ms' => null,
                'detected_faces_count' => 0,
                'matched_annotations_count' => 0,
                'matched_detections_count' => 0,
                'annotation_recall_estimated' => 0.0,
                'detection_precision_estimated' => null,
                'detected_face_min_side_px_min' => null,
                'detected_face_min_side_px_p50' => null,
                'detected_face_min_side_px_p95' => null,
                'detected_face_min_side_px_max' => null,
                'best_iou' => null,
                'error' => 'empty_image_binary',
            ];
        }

        $media = new EventMedia([
            'media_type' => 'image',
            'mime_type' => File::mimeType($path) ?: 'image/png',
            'original_filename' => basename($path),
            'source_type' => 'detection-dataset-probe',
        ]);

        try {
            $startedAt = microtime(true);
            $faces = array_values($this->detector->detect($media, $settings, $binary));
            $latencyMs = round((microtime(true) - $startedAt) * 1000, 2);
            $matches = $this->matchAnnotations((array) $entry['annotations'], $faces, $iouThreshold);
            $faceSides = collect($faces)
                ->map(fn (DetectedFaceData $face): float => (float) min($face->boundingBox->width, $face->boundingBox->height))
                ->sort()
                ->values()
                ->all();

            $annotationCount = (int) $entry['annotation_count'];
            $detectedCount = count($faces);
            $matchedAnnotations = count($matches['matched_annotation_indices']);
            $matchedDetections = count($matches['matched_detection_indices']);

            return [
                ...$reportEntry,
                'status' => 'success',
                'latency_ms' => $latencyMs,
                'detected_faces_count' => $detectedCount,
                'matched_annotations_count' => $matchedAnnotations,
                'matched_detections_count' => $matchedDetections,
                'annotation_recall_estimated' => $annotationCount > 0
                    ? round($matchedAnnotations / $annotationCount, 4)
                    : null,
                'detection_precision_estimated' => $detectedCount > 0
                    ? round($matchedDetections / $detectedCount, 4)
                    : null,
                'detected_face_min_side_px_min' => $this->percentile($faceSides, 0),
                'detected_face_min_side_px_p50' => $this->percentile($faceSides, 50),
                'detected_face_min_side_px_p95' => $this->percentile($faceSides, 95),
                'detected_face_min_side_px_max' => $this->percentile($faceSides, 100),
                'best_iou' => $matches['best_iou'],
                'matched_pairs' => $matches['matched_pairs'],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                ...$reportEntry,
                'status' => 'failed',
                'latency_ms' => null,
                'detected_faces_count' => 0,
                'matched_annotations_count' => 0,
                'matched_detections_count' => 0,
                'annotation_recall_estimated' => 0.0,
                'detection_precision_estimated' => null,
                'detected_face_min_side_px_min' => null,
                'detected_face_min_side_px_p50' => null,
                'detected_face_min_side_px_p95' => null,
                'detected_face_min_side_px_max' => null,
                'best_iou' => null,
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
        $copy = $entry;
        unset($copy['annotations']);

        return $copy;
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
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildSummary(array $rows): array
    {
        $successful = collect($rows)->where('status', 'success')->values();
        $latencies = $successful->pluck('latency_ms')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): float => (float) $value)->values()->all();
        $allRows = collect($rows);
        $annotationTotal = (int) $allRows->sum('annotation_count');
        $detectedTotal = (int) $allRows->sum('detected_faces_count');
        $matchedAnnotationTotal = (int) $allRows->sum('matched_annotations_count');
        $matchedDetectionTotal = (int) $allRows->sum('matched_detections_count');

        return [
            'images_sampled' => count($rows),
            'images_with_successful_detection' => $successful->count(),
            'images_failed_or_missing' => count($rows) - $successful->count(),
            'annotated_faces_total' => $annotationTotal,
            'detected_faces_total' => $detectedTotal,
            'matched_annotations_total' => $matchedAnnotationTotal,
            'matched_detections_total' => $matchedDetectionTotal,
            'annotation_recall_estimated' => $annotationTotal > 0 ? round($matchedAnnotationTotal / $annotationTotal, 4) : null,
            'detection_precision_estimated' => $detectedTotal > 0 ? round($matchedDetectionTotal / $detectedTotal, 4) : null,
            'p50_detect_latency_ms' => $this->percentile($latencies, 50),
            'p95_detect_latency_ms' => $this->percentile($latencies, 95),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildBreakdown(array $rows, callable $groupKey): array
    {
        return collect($rows)
            ->groupBy(fn (array $row): string => $groupKey($row))
            ->map(function (Collection $group, string $key): array {
                $annotationTotal = (int) $group->sum('annotation_count');
                $detectedTotal = (int) $group->sum('detected_faces_count');
                $matchedAnnotationTotal = (int) $group->sum('matched_annotations_count');
                $matchedDetectionTotal = (int) $group->sum('matched_detections_count');

                return [
                    'bucket' => $key,
                    'images' => $group->count(),
                    'annotated_faces_total' => $annotationTotal,
                    'detected_faces_total' => $detectedTotal,
                    'matched_annotations_total' => $matchedAnnotationTotal,
                    'matched_detections_total' => $matchedDetectionTotal,
                    'annotation_recall_estimated' => $annotationTotal > 0 ? round($matchedAnnotationTotal / $annotationTotal, 4) : null,
                    'detection_precision_estimated' => $detectedTotal > 0 ? round($matchedDetectionTotal / $detectedTotal, 4) : null,
                ];
            })
            ->values()
            ->all();
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
