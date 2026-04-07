<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class FaceSearchBenchmarkService
{
    public function __construct(
        private readonly FaceVectorStoreInterface $vectorStore,
    ) {}

    /**
     * @param array<int, string> $strategies
     * @return array<string, mixed>
     */
    public function run(
        string $smokeReportPath,
        array $strategies = ['exact', 'ann'],
        int $topK = 5,
        ?float $threshold = null,
    ): array {
        $report = $this->loadSmokeReport($smokeReportPath);
        $detections = $this->normalizeDetections($report);
        $strategies = $this->normalizeStrategies($strategies);

        if ($strategies === []) {
            throw new RuntimeException('FaceSearch benchmark requires at least one valid search strategy.');
        }

        $threshold ??= (float) config('face_search.search_threshold', 0.50);
        $topK = max(1, $topK);

        $strategyReports = array_map(
            fn (string $strategy): array => $this->benchmarkStrategy($detections, $strategy, $topK, $threshold),
            $strategies,
        );

        return [
            'source_smoke_report' => $smokeReportPath,
            'entries_count' => $detections->count(),
            'dataset_summary' => $this->datasetSummary($report, $detections),
            'top_k' => $topK,
            'threshold' => $threshold,
            'strategies' => $strategyReports,
            'operational_summary' => [
                'p95_detect_ms' => $this->percentile(
                    $detections->pluck('detector_latency_ms')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->all(),
                    95,
                ),
                'p95_embed_ms' => $this->percentile(
                    $detections->pluck('calculator_latency_ms')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->all(),
                    95,
                ),
                'selfie_rejection_rate' => $this->selfieRejectionRate($report, $detections->count()),
                'throughput_face_index_per_minute' => $this->estimateThroughputPerMinute($detections),
                'provider_saturation' => 'not_measured_in_local_benchmark',
                'slowest_detection_ids' => $detections
                    ->sortByDesc(fn (array $entry): float => (float) ($entry['latency_ms'] ?? 0.0))
                    ->take(3)
                    ->map(fn (array $entry): array => [
                        'id' => $entry['id'],
                        'latency_ms' => is_numeric($entry['latency_ms'] ?? null) ? (float) $entry['latency_ms'] : null,
                        'calculator_latency_ms' => is_numeric($entry['calculator_latency_ms'] ?? null) ? (float) $entry['calculator_latency_ms'] : null,
                        'quality_label' => $entry['quality_label'] ?? null,
                        'scene_type' => $entry['scene_type'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ],
            'request_outcome' => 'success',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSmokeReport(string $smokeReportPath): array
    {
        if ($smokeReportPath === '') {
            throw new RuntimeException('FaceSearch benchmark requires --smoke-report pointing to a real smoke JSON report.');
        }

        $resolvedPath = File::exists($smokeReportPath)
            ? $smokeReportPath
            : base_path(ltrim($smokeReportPath, '\\/'));

        if (! File::exists($resolvedPath)) {
            throw new RuntimeException(sprintf('FaceSearch benchmark smoke report [%s] does not exist.', $smokeReportPath));
        }

        $payload = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('FaceSearch benchmark smoke report is invalid.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $report
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeDetections(array $report): Collection
    {
        $detections = collect((array) ($report['detections'] ?? []))
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(function (array $entry): array {
                $embedding = collect((array) ($entry['embedding'] ?? []))
                    ->filter(fn (mixed $value): bool => is_numeric($value))
                    ->map(fn (mixed $value): float => (float) $value)
                    ->values()
                    ->all();

                return [
                    'id' => (string) ($entry['id'] ?? ''),
                    'event_id' => (string) ($entry['event_id'] ?? 'benchmark-local-event'),
                    'person_id' => (string) ($entry['person_id'] ?? ''),
                    'expected_positive_set' => array_values(array_map('strval', (array) ($entry['expected_positive_set'] ?? []))),
                    'embedding' => $embedding,
                    'quality_label' => (string) ($entry['quality_label'] ?? 'unknown'),
                    'scene_type' => (string) ($entry['scene_type'] ?? 'unknown'),
                    'detected_faces_count' => is_numeric($entry['detected_faces_count'] ?? null) ? (int) $entry['detected_faces_count'] : 1,
                    'latency_ms' => is_numeric($entry['latency_ms'] ?? null) ? (float) $entry['latency_ms'] : null,
                    'detector_latency_ms' => is_numeric($entry['detector_latency_ms'] ?? null) ? (float) $entry['detector_latency_ms'] : null,
                    'calculator_latency_ms' => is_numeric($entry['calculator_latency_ms'] ?? null) ? (float) $entry['calculator_latency_ms'] : null,
                ];
            })
            ->filter(fn (array $entry): bool => $entry['id'] !== '' && $entry['person_id'] !== '' && $entry['embedding'] !== []);

        if ($detections->count() < 2) {
            throw new RuntimeException('FaceSearch benchmark requires at least two detected embeddings in the smoke report.');
        }

        if ($detections->pluck('person_id')->unique()->count() < 2) {
            throw new RuntimeException('FaceSearch benchmark requires at least two different people in the smoke report.');
        }

        return $detections->values();
    }

    /**
     * @param array<int, string> $strategies
     * @return array<int, string>
     */
    private function normalizeStrategies(array $strategies): array
    {
        return collect($strategies)
            ->map(fn (mixed $strategy): string => strtolower(trim((string) $strategy)))
            ->filter(fn (string $strategy): bool => in_array($strategy, ['exact', 'ann'], true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $detections
     * @return array<string, mixed>
     */
    private function benchmarkStrategy(Collection $detections, string $strategy, int $topK, float $threshold): array
    {
        DB::beginTransaction();

        try {
            $mapping = $this->seedBenchmarkDataset($detections);
            $evaluations = [];

            foreach ($detections as $detection) {
                $queryId = (string) $detection['id'];
                $queryMapping = $mapping[$queryId] ?? null;

                if (! is_array($queryMapping)) {
                    continue;
                }

                $expectedMediaIds = collect($mapping)
                    ->filter(fn (array $candidate, string $candidateId): bool => $candidate['person_id'] === $queryMapping['person_id'] && $candidateId !== $queryId)
                    ->map(fn (array $candidate): int => $candidate['event_media_id'])
                    ->values()
                    ->all();

                if ($expectedMediaIds === []) {
                    continue;
                }

                $startedAt = microtime(true);
                $matches = collect($this->vectorStore->search(
                    eventId: $queryMapping['event_id'],
                    queryEmbedding: $detection['embedding'],
                    topK: max($topK + 1, 6),
                    threshold: $threshold,
                    searchableOnly: true,
                    searchStrategy: $strategy,
                ))
                    ->reject(fn ($match) => $match->eventMediaId === $queryMapping['event_media_id'])
                    ->values();
                $searchLatencyMs = (microtime(true) - $startedAt) * 1000;

                $top1 = $matches->get(0);
                $top1Hit = $top1 !== null && in_array($top1->eventMediaId, $expectedMediaIds, true);
                $falsePositiveTop1 = $top1 !== null && ! $top1Hit;
                $topKHit = $matches->take($topK)->contains(
                    fn ($match) => in_array($match->eventMediaId, $expectedMediaIds, true),
                );

                $evaluations[] = [
                    'query_id' => $queryId,
                    'scene_type' => (string) ($detection['scene_type'] ?? 'unknown'),
                    'quality_label' => (string) ($detection['quality_label'] ?? 'unknown'),
                    'detected_faces_count' => max(1, (int) ($detection['detected_faces_count'] ?? 1)),
                    'search_latency_ms' => $searchLatencyMs,
                    'top_1_hit' => $top1Hit,
                    'top_k_hit' => $topKHit,
                    'false_positive_top_1' => $falsePositiveTop1,
                ];
            }

            $summary = $this->summarizeEvaluations($evaluations);

            return [
                'search_strategy' => $strategy,
                'queries_evaluated' => $summary['queries_evaluated'],
                'top_1_hit_rate' => $summary['top_1_hit_rate'],
                'top_5_hit_rate' => $summary['top_5_hit_rate'],
                'false_positive_top_1_rate' => $summary['false_positive_top_1_rate'],
                'selfie_rejection_rate' => 0.0,
                'p95_search_ms' => $summary['p95_search_ms'],
                'threshold' => $threshold,
                'top_k' => $topK,
                'scene_type_breakdown' => $this->breakdownEvaluations($evaluations, 'scene_type'),
                'quality_label_breakdown' => $this->breakdownEvaluations($evaluations, 'quality_label'),
                'detected_faces_count_breakdown' => $this->breakdownEvaluations($evaluations, 'detected_faces_count'),
            ];
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param Collection<int, array<string, mixed>> $detections
     * @return array<string, array{event_id:int,event_media_id:int,person_id:string}>
     */
    private function seedBenchmarkDataset(Collection $detections): array
    {
        $mapping = [];

        foreach ($detections->groupBy('event_id') as $sourceEventId => $eventDetections) {
            $organization = Organization::query()->create([
                'trade_name' => 'FaceSearch Benchmark',
                'legal_name' => 'FaceSearch Benchmark LTDA',
                'slug' => 'face-search-benchmark-' . Str::lower(Str::random(8)),
                'type' => 'internal',
                'status' => 'active',
                'email' => 'benchmark@example.test',
                'billing_email' => 'benchmark@example.test',
                'phone' => '0000-0000',
                'timezone' => 'America/Sao_Paulo',
            ]);

            $event = Event::query()->create([
                'organization_id' => $organization->id,
                'title' => 'FaceSearch Benchmark ' . $sourceEventId,
                'slug' => 'face-search-benchmark-' . Str::lower(Str::random(10)),
                'event_type' => 'wedding',
                'status' => 'active',
                'visibility' => 'public',
                'moderation_mode' => 'manual',
                'starts_at' => now(),
                'ends_at' => now()->addHours(4),
                'location_name' => 'Benchmark',
                'description' => 'Temporary benchmark dataset',
                'retention_days' => 1,
                'commercial_mode' => 'none',
            ]);

            foreach ($eventDetections as $index => $detection) {
                $media = EventMedia::query()->create([
                    'event_id' => $event->id,
                    'media_type' => 'image',
                    'source_type' => 'benchmark',
                    'source_label' => 'face-search-benchmark',
                    'original_filename' => sprintf('%s.jpg', $detection['id']),
                    'client_filename' => sprintf('%s.jpg', $detection['id']),
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => 1000,
                    'width' => 100,
                    'height' => 100,
                    'processing_status' => 'received',
                    'moderation_status' => 'approved',
                    'publication_status' => 'published',
                    'published_at' => now(),
                    'pipeline_version' => 'face-search-benchmark-v1',
                ]);

                EventMediaFace::query()->create([
                    'event_id' => $event->id,
                    'event_media_id' => $media->id,
                    'face_index' => $index,
                    'bbox_x' => 10,
                    'bbox_y' => 10,
                    'bbox_w' => 160,
                    'bbox_h' => 160,
                    'detection_confidence' => 0.99,
                    'quality_score' => 0.9,
                    'quality_tier' => 'search_priority',
                    'sharpness_score' => 0.9,
                    'face_area_ratio' => 0.18,
                    'pose_yaw' => 0.0,
                    'pose_pitch' => 0.0,
                    'pose_roll' => 0.0,
                    'searchable' => true,
                    'crop_disk' => 'ai-private',
                    'crop_path' => sprintf('benchmark/%s.webp', $detection['id']),
                    'embedding_model_key' => 'face-embedding-foundation-v1',
                    'embedding_version' => 'benchmark-v1',
                    'vector_store_key' => 'pgvector',
                    'face_hash' => sprintf('benchmark-%s', $detection['id']),
                    'is_primary_face_candidate' => true,
                    'embedding' => $this->serializeVector($detection['embedding']),
                ]);

                $mapping[(string) $detection['id']] = [
                    'event_id' => $event->id,
                    'event_media_id' => $media->id,
                    'person_id' => (string) $detection['person_id'],
                ];
            }
        }

        return $mapping;
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
        $position = (int) ceil(($percentile / 100) * count($values)) - 1;
        $position = max(0, min($position, count($values) - 1));

        return round((float) $values[$position], 2);
    }

    /**
     * @param array<string, mixed> $report
     */
    private function selfieRejectionRate(array $report, int $successfulDetections): float
    {
        $entriesCount = count((array) ($report['entries'] ?? []));

        if ($entriesCount <= 0) {
            return 0.0;
        }

        return round(max(0, $entriesCount - $successfulDetections) / $entriesCount, 4);
    }

    /**
     * @param Collection<int, array<string, mixed>> $detections
     */
    private function estimateThroughputPerMinute(Collection $detections): ?float
    {
        $totalLatencyMs = $detections
            ->pluck('latency_ms')
            ->filter(fn (mixed $value): bool => is_numeric($value) && (float) $value > 0)
            ->map(fn (mixed $value): float => (float) $value)
            ->sum();

        if ($totalLatencyMs <= 0 || $detections->isEmpty()) {
            return null;
        }

        return round(($detections->count() / $totalLatencyMs) * 60000, 2);
    }

    /**
     * @param array<string, mixed> $report
     * @param Collection<int, array<string, mixed>> $detections
     * @return array<string, mixed>
     */
    private function datasetSummary(array $report, Collection $detections): array
    {
        return [
            'unique_people' => $detections->pluck('person_id')->unique()->count(),
            'unique_events' => $detections->pluck('event_id')->unique()->count(),
            'multi_face_entries' => $detections->filter(
                fn (array $entry): bool => (int) ($entry['detected_faces_count'] ?? 1) > 1,
            )->count(),
            'source_smoke_threshold_tested' => is_numeric($report['threshold_tested'] ?? null)
                ? (float) $report['threshold_tested']
                : null,
            'scene_type_counts' => $this->countBreakdown($detections, 'scene_type'),
            'quality_label_counts' => $this->countBreakdown($detections, 'quality_label'),
            'detected_faces_count_counts' => $this->countBreakdown($detections, 'detected_faces_count'),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $detections
     * @return array<int, array<string, int|string>>
     */
    private function countBreakdown(Collection $detections, string $dimension): array
    {
        $breakdown = $detections
            ->groupBy(fn (array $entry): string => (string) ($entry[$dimension] ?? 'unknown'))
            ->map(function (Collection $group, string $key) use ($dimension): array {
                $value = $dimension === 'detected_faces_count' ? (int) $key : $key;

                return [
                    $dimension => $value,
                    'count' => $group->count(),
                ];
            })
            ->values()
            ->all();

        usort($breakdown, function (array $left, array $right) use ($dimension): int {
            if ($dimension === 'detected_faces_count') {
                return ((int) $left[$dimension]) <=> ((int) $right[$dimension]);
            }

            $count = $right['count'] <=> $left['count'];

            if ($count !== 0) {
                return $count;
            }

            return strcmp((string) $left[$dimension], (string) $right[$dimension]);
        });

        return $breakdown;
    }

    /**
     * @param array<int, array<string, mixed>> $evaluations
     * @return array<string, int|float|null>
     */
    private function summarizeEvaluations(array $evaluations): array
    {
        $queriesEvaluated = count($evaluations);
        $searchLatencies = array_values(array_map(
            static fn (array $evaluation): float => (float) ($evaluation['search_latency_ms'] ?? 0.0),
            $evaluations,
        ));
        $top1Hits = count(array_filter(
            $evaluations,
            static fn (array $evaluation): bool => (bool) ($evaluation['top_1_hit'] ?? false),
        ));
        $topKHits = count(array_filter(
            $evaluations,
            static fn (array $evaluation): bool => (bool) ($evaluation['top_k_hit'] ?? false),
        ));
        $falsePositiveTop1 = count(array_filter(
            $evaluations,
            static fn (array $evaluation): bool => (bool) ($evaluation['false_positive_top_1'] ?? false),
        ));

        return [
            'queries_evaluated' => $queriesEvaluated,
            'top_1_hit_rate' => $queriesEvaluated > 0 ? round($top1Hits / $queriesEvaluated, 4) : null,
            'top_5_hit_rate' => $queriesEvaluated > 0 ? round($topKHits / $queriesEvaluated, 4) : null,
            'false_positive_top_1_rate' => $queriesEvaluated > 0 ? round($falsePositiveTop1 / $queriesEvaluated, 4) : null,
            'p95_search_ms' => $this->percentile($searchLatencies, 95),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $evaluations
     * @return array<int, array<string, int|float|null|string>>
     */
    private function breakdownEvaluations(array $evaluations, string $dimension): array
    {
        $breakdown = collect($evaluations)
            ->groupBy(fn (array $evaluation): string => (string) ($evaluation[$dimension] ?? 'unknown'))
            ->map(function (Collection $group, string $key) use ($dimension): array {
                $summary = $this->summarizeEvaluations($group->values()->all());
                $value = $dimension === 'detected_faces_count' ? (int) $key : $key;

                return [
                    $dimension => $value,
                    ...$summary,
                ];
            })
            ->values()
            ->all();

        usort($breakdown, function (array $left, array $right) use ($dimension): int {
            if ($dimension === 'detected_faces_count') {
                return ((int) $left[$dimension]) <=> ((int) $right[$dimension]);
            }

            $queries = ((int) $right['queries_evaluated']) <=> ((int) $left['queries_evaluated']);

            if ($queries !== 0) {
                return $queries;
            }

            return strcmp((string) $left[$dimension], (string) $right[$dimension]);
        });

        return $breakdown;
    }

    /**
     * @param array<int, float> $vector
     */
    private function serializeVector(array $vector): string
    {
        return '[' . collect($vector)
            ->map(fn ($value) => rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.'))
            ->map(fn ($value) => $value === '' ? '0' : $value)
            ->implode(',') . ']';
    }
}
