<?php

namespace App\Modules\FaceSearch\Services;

use RuntimeException;

class FaceSearchThresholdSweepService
{
    public function __construct(
        private readonly FaceSearchBenchmarkService $benchmarkService,
    ) {}

    /**
     * @param array<int, string|float|int> $thresholds
     * @param array<int, string> $strategies
     * @return array<string, mixed>
     */
    public function run(
        string $smokeReportPath,
        array $thresholds = [],
        array $strategies = ['exact', 'ann'],
        int $topK = 5,
    ): array {
        $normalizedThresholds = $this->normalizeThresholds($thresholds);

        if ($normalizedThresholds === []) {
            throw new RuntimeException('FaceSearch threshold sweep requires at least one valid threshold.');
        }

        $runs = [];

        foreach ($normalizedThresholds as $threshold) {
            $runs[] = $this->benchmarkService->run(
                smokeReportPath: $smokeReportPath,
                strategies: $strategies,
                topK: $topK,
                threshold: $threshold,
            );
        }

        $baseline = $runs[0] ?? [];

        return [
            'source_smoke_report' => $smokeReportPath,
            'top_k' => max(1, $topK),
            'thresholds_tested' => $normalizedThresholds,
            'metric_semantics' => [
                'app_threshold_kind' => 'pgvector_cosine_distance_upper_bound',
                'provider_similarity_reference_kind' => 'compreface_similarity_reference',
                'notes' => [
                    'The app filters pgvector matches with cosine distance using the <=> operator, so lower thresholds are stricter.',
                    'CompreFace verification exposes similarity, not pgvector distance, so provider similarity guidance must not be copied 1:1 into app threshold values.',
                ],
            ],
            'dataset_summary' => $baseline['dataset_summary'] ?? [],
            'runs' => array_map(function (array $run): array {
                return [
                    'threshold' => (float) ($run['threshold'] ?? 0.0),
                    'entries_count' => (int) ($run['entries_count'] ?? 0),
                    'strategies' => array_values((array) ($run['strategies'] ?? [])),
                    'operational_summary' => $run['operational_summary'] ?? [],
                ];
            }, $runs),
            'recommendations' => $this->buildRecommendations($runs),
            'request_outcome' => 'success',
        ];
    }

    /**
     * @param array<int, string|float|int> $thresholds
     * @return array<int, float>
     */
    private function normalizeThresholds(array $thresholds): array
    {
        $normalized = collect($thresholds)
            ->map(function (mixed $threshold): array {
                if (is_string($threshold)) {
                    return explode(',', $threshold);
                }

                return [$threshold];
            })
            ->flatten()
            ->filter(fn (mixed $threshold): bool => is_numeric($threshold))
            ->map(fn (mixed $threshold): float => round((float) $threshold, 4))
            ->filter(fn (float $threshold): bool => $threshold >= 0.0 && $threshold <= 2.0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        return [0.30, 0.35, 0.40, 0.45, 0.50, 0.55, 0.60];
    }

    /**
     * @param array<int, array<string, mixed>> $runs
     * @return array<int, array<string, mixed>>
     */
    private function buildRecommendations(array $runs): array
    {
        $candidates = collect($runs)
            ->flatMap(function (array $run): array {
                $threshold = (float) ($run['threshold'] ?? 0.0);

                return collect((array) ($run['strategies'] ?? []))
                    ->filter(fn (mixed $strategy): bool => is_array($strategy) && isset($strategy['search_strategy']))
                    ->map(fn (array $strategy): array => [
                        'search_strategy' => (string) $strategy['search_strategy'],
                        'threshold' => $threshold,
                        'queries_evaluated' => (int) ($strategy['queries_evaluated'] ?? 0),
                        'top_1_hit_rate' => is_numeric($strategy['top_1_hit_rate'] ?? null) ? (float) $strategy['top_1_hit_rate'] : null,
                        'top_5_hit_rate' => is_numeric($strategy['top_5_hit_rate'] ?? null) ? (float) $strategy['top_5_hit_rate'] : null,
                        'false_positive_top_1_rate' => is_numeric($strategy['false_positive_top_1_rate'] ?? null) ? (float) $strategy['false_positive_top_1_rate'] : null,
                        'p95_search_ms' => is_numeric($strategy['p95_search_ms'] ?? null) ? (float) $strategy['p95_search_ms'] : null,
                    ])
                    ->values()
                    ->all();
            })
            ->groupBy('search_strategy');

        return $candidates
            ->map(function ($group, string $strategy): array {
                $rows = $group->values()->all();
                usort($rows, function (array $left, array $right): int {
                    $leftNetTop1 = $this->netTop1Score($left);
                    $rightNetTop1 = $this->netTop1Score($right);
                    $netTop1 = $rightNetTop1 <=> $leftNetTop1;

                    if ($netTop1 !== 0) {
                        return $netTop1;
                    }

                    $leftFalsePositive = $left['false_positive_top_1_rate'] ?? INF;
                    $rightFalsePositive = $right['false_positive_top_1_rate'] ?? INF;
                    $falsePositive = $leftFalsePositive <=> $rightFalsePositive;

                    if ($falsePositive !== 0) {
                        return $falsePositive;
                    }

                    $top1 = ($right['top_1_hit_rate'] ?? -INF) <=> ($left['top_1_hit_rate'] ?? -INF);

                    if ($top1 !== 0) {
                        return $top1;
                    }

                    $top5 = ($right['top_5_hit_rate'] ?? -INF) <=> ($left['top_5_hit_rate'] ?? -INF);

                    if ($top5 !== 0) {
                        return $top5;
                    }

                    $threshold = ($left['threshold'] ?? INF) <=> ($right['threshold'] ?? INF);

                    if ($threshold !== 0) {
                        return $threshold;
                    }

                    return ($left['p95_search_ms'] ?? INF) <=> ($right['p95_search_ms'] ?? INF);
                });

                $best = $rows[0] ?? [
                    'threshold' => null,
                    'queries_evaluated' => 0,
                    'top_1_hit_rate' => null,
                    'top_5_hit_rate' => null,
                    'false_positive_top_1_rate' => null,
                    'p95_search_ms' => null,
                ];

                return [
                    'search_strategy' => $strategy,
                    'recommended_threshold' => $best['threshold'],
                    'queries_evaluated' => $best['queries_evaluated'],
                    'top_1_hit_rate' => $best['top_1_hit_rate'],
                    'top_5_hit_rate' => $best['top_5_hit_rate'],
                    'false_positive_top_1_rate' => $best['false_positive_top_1_rate'],
                    'p95_search_ms' => $best['p95_search_ms'],
                    'net_top_1_score' => $this->netTop1Score($best),
                    'selection_rule' => 'max_top1_minus_false_positive_then_min_false_positive_then_max_top1_then_max_top5_then_min_threshold_then_min_latency',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function netTop1Score(array $candidate): float
    {
        $top1 = is_numeric($candidate['top_1_hit_rate'] ?? null)
            ? (float) $candidate['top_1_hit_rate']
            : -INF;
        $falsePositive = is_numeric($candidate['false_positive_top_1_rate'] ?? null)
            ? (float) $candidate['false_positive_top_1_rate']
            : INF;

        return round($top1 - $falsePositive, 4);
    }
}
