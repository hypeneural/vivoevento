<?php

namespace App\Modules\FaceSearch\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SmokeMinFaceSizeAnalysisService
{
    /**
     * @param array<int, int|string> $thresholds
     * @return array<string, mixed>
     */
    public function run(
        string $smokeReportPath,
        array $thresholds = [16, 24, 32, 40, 48, 64, 96],
        float $minRetainedRate = 1.0,
    ): array {
        $report = $this->loadSmokeReport($smokeReportPath);
        $detections = $this->normalizeDetections($report);
        $thresholds = $this->normalizeThresholds($thresholds);

        if ($thresholds === []) {
            throw new RuntimeException('Smoke min-face-size analysis requires at least one valid threshold.');
        }

        if ($detections->isEmpty()) {
            throw new RuntimeException('Smoke min-face-size analysis requires at least one detection row.');
        }

        $baselineSummary = $this->buildBaselineSummary($report, $detections);
        $thresholdBreakdown = collect($thresholds)
            ->map(fn (int $threshold): array => $this->buildThresholdMetrics($detections, $threshold))
            ->values()
            ->all();

        return [
            'source_smoke_report' => $this->resolvePath($smokeReportPath),
            'provider' => (string) ($report['provider'] ?? 'unknown'),
            'manifest_path' => (string) ($report['manifest_path'] ?? ''),
            'thresholds' => $thresholds,
            'min_retained_rate' => round(max(0.0, min(1.0, $minRetainedRate)), 4),
            'baseline_summary' => $baselineSummary,
            'threshold_breakdown' => $thresholdBreakdown,
            'recommended_threshold' => $this->recommendThreshold($thresholdBreakdown, $minRetainedRate),
            'request_outcome' => (string) ($report['request_outcome'] ?? 'unknown'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSmokeReport(string $smokeReportPath): array
    {
        if ($smokeReportPath === '') {
            throw new RuntimeException('Smoke min-face-size analysis requires --smoke-report pointing to a real smoke JSON report.');
        }

        $resolvedPath = $this->resolvePath($smokeReportPath);

        if (! File::exists($resolvedPath)) {
            throw new RuntimeException(sprintf('Smoke min-face-size report [%s] does not exist.', $smokeReportPath));
        }

        $payload = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('Smoke min-face-size report is invalid.');
        }

        return $payload;
    }

    private function resolvePath(string $path): string
    {
        return File::exists($path)
            ? $path
            : base_path(ltrim($path, '\\/'));
    }

    /**
     * @param array<string, mixed> $report
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeDetections(array $report): Collection
    {
        return collect((array) ($report['detections'] ?? []))
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(function (array $entry): array {
                $box = is_array($entry['selected_face_bbox'] ?? null) ? $entry['selected_face_bbox'] : null;
                $minSide = $this->selectedFaceMinSide($box);

                return [
                    'id' => (string) ($entry['id'] ?? ''),
                    'person_id' => (string) ($entry['person_id'] ?? ''),
                    'scene_type' => (string) ($entry['scene_type'] ?? 'unknown'),
                    'quality_label' => (string) ($entry['quality_label'] ?? 'unknown'),
                    'request_outcome' => (string) ($entry['request_outcome'] ?? 'unknown'),
                    'detected_faces_count' => is_numeric($entry['detected_faces_count'] ?? null) ? (int) $entry['detected_faces_count'] : 0,
                    'selected_face_min_side_px' => $minSide,
                ];
            })
            ->filter(fn (array $entry): bool => $entry['id'] !== '')
            ->values();
    }

    /**
     * @param array<string, mixed>|null $box
     */
    private function selectedFaceMinSide(?array $box): ?float
    {
        if ($box === null) {
            return null;
        }

        $width = $this->numeric($box['x_max'] ?? null) - $this->numeric($box['x_min'] ?? null);
        $height = $this->numeric($box['y_max'] ?? null) - $this->numeric($box['y_min'] ?? null);

        if ($width === null || $height === null || $width <= 0 || $height <= 0) {
            return null;
        }

        return round(min($width, $height), 2);
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
     * @param array<string, mixed> $report
     * @param Collection<int, array<string, mixed>> $detections
     * @return array<string, mixed>
     */
    private function buildBaselineSummary(array $report, Collection $detections): array
    {
        $selectedSides = $detections
            ->pluck('selected_face_min_side_px')
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): float => (float) $value)
            ->values()
            ->all();
        $successCount = $detections->where('request_outcome', 'success')->count();
        $failedCount = $detections->count() - $successCount;
        $sceneBreakdown = $this->buildGroupSummary($detections, fn (array $entry): string => $entry['scene_type']);
        $qualityBreakdown = $this->buildGroupSummary($detections, fn (array $entry): string => $entry['quality_label']);

        return [
            'entries_count' => $detections->count(),
            'entries_successful' => $successCount,
            'entries_failed_or_degraded' => $failedCount,
            'selected_face_min_side_px_min' => $this->percentile($selectedSides, 0),
            'selected_face_min_side_px_p50' => $this->percentile($selectedSides, 50),
            'selected_face_min_side_px_p95' => $this->percentile($selectedSides, 95),
            'selected_face_min_side_px_max' => $this->percentile($selectedSides, 100),
            'scene_breakdown' => $sceneBreakdown,
            'quality_breakdown' => $qualityBreakdown,
            'provider_request_outcome' => (string) ($report['request_outcome'] ?? 'unknown'),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $detections
     * @return array<string, mixed>
     */
    private function buildThresholdMetrics(Collection $detections, int $threshold): array
    {
        $successful = $detections->where('request_outcome', 'success')->values();
        $retained = $successful
            ->filter(fn (array $entry): bool => is_numeric($entry['selected_face_min_side_px'] ?? null) && (float) $entry['selected_face_min_side_px'] >= $threshold)
            ->values();

        return [
            'threshold' => $threshold,
            'successful_entries_total' => $successful->count(),
            'retained_entries_total' => $retained->count(),
            'retained_entry_rate' => $successful->count() > 0 ? round($retained->count() / $successful->count(), 4) : null,
            'retained_person_rate' => $successful->pluck('person_id')->filter()->unique()->count() > 0
                ? round($retained->pluck('person_id')->filter()->unique()->count() / $successful->pluck('person_id')->filter()->unique()->count(), 4)
                : null,
            'scene_breakdown' => $this->buildThresholdGroupSummary($successful, $retained, fn (array $entry): string => $entry['scene_type']),
            'quality_breakdown' => $this->buildThresholdGroupSummary($successful, $retained, fn (array $entry): string => $entry['quality_label']),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function buildGroupSummary(Collection $entries, callable $key): array
    {
        return $entries
            ->groupBy(fn (array $entry): string => $key($entry))
            ->map(function (Collection $group, string $bucket): array {
                $sides = $group->pluck('selected_face_min_side_px')
                    ->filter(fn ($value): bool => is_numeric($value))
                    ->map(fn ($value): float => (float) $value)
                    ->values()
                    ->all();

                return [
                    'bucket' => $bucket,
                    'entries' => $group->count(),
                    'selected_face_min_side_px_min' => $this->percentile($sides, 0),
                    'selected_face_min_side_px_p50' => $this->percentile($sides, 50),
                    'selected_face_min_side_px_p95' => $this->percentile($sides, 95),
                    'selected_face_min_side_px_max' => $this->percentile($sides, 100),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $successful
     * @param Collection<int, array<string, mixed>> $retained
     * @return array<int, array<string, mixed>>
     */
    private function buildThresholdGroupSummary(Collection $successful, Collection $retained, callable $key): array
    {
        return $successful
            ->groupBy(fn (array $entry): string => $key($entry))
            ->map(function (Collection $group, string $bucket) use ($retained, $key): array {
                $retainedGroup = $retained->filter(fn (array $entry): bool => $key($entry) === $bucket)->values();

                return [
                    'bucket' => $bucket,
                    'successful_entries_total' => $group->count(),
                    'retained_entries_total' => $retainedGroup->count(),
                    'retained_entry_rate' => $group->count() > 0 ? round($retainedGroup->count() / $group->count(), 4) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $thresholdBreakdown
     * @return array<string, mixed>|null
     */
    private function recommendThreshold(array $thresholdBreakdown, float $minRetainedRate): ?array
    {
        $minRetainedRate = max(0.0, min(1.0, $minRetainedRate));

        $eligible = collect($thresholdBreakdown)
            ->filter(fn (array $row): bool => is_numeric($row['retained_entry_rate'] ?? null) && (float) $row['retained_entry_rate'] >= $minRetainedRate)
            ->sortByDesc('threshold')
            ->values();

        if ($eligible->isNotEmpty()) {
            $selected = (array) $eligible->first();

            return [
                'threshold' => (int) ($selected['threshold'] ?? 0),
                'retained_entry_rate' => $selected['retained_entry_rate'] ?? null,
                'retained_person_rate' => $selected['retained_person_rate'] ?? null,
                'selection_rule' => 'max_threshold_with_retained_entry_rate_at_or_above_target',
            ];
        }

        $fallback = collect($thresholdBreakdown)
            ->sort(function (array $left, array $right): int {
                $comparisons = [
                    ((float) ($right['retained_entry_rate'] ?? -1.0)) <=> ((float) ($left['retained_entry_rate'] ?? -1.0)),
                    ((float) ($right['retained_person_rate'] ?? -1.0)) <=> ((float) ($left['retained_person_rate'] ?? -1.0)),
                    ((int) ($left['threshold'] ?? PHP_INT_MAX)) <=> ((int) ($right['threshold'] ?? PHP_INT_MAX)),
                ];

                foreach ($comparisons as $comparison) {
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            })
            ->values()
            ->first();

        if (! is_array($fallback)) {
            return null;
        }

        return [
            'threshold' => (int) ($fallback['threshold'] ?? 0),
            'retained_entry_rate' => $fallback['retained_entry_rate'] ?? null,
            'retained_person_rate' => $fallback['retained_person_rate'] ?? null,
            'selection_rule' => 'max_retained_entry_rate_then_max_retained_person_rate_then_min_threshold',
        ];
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
