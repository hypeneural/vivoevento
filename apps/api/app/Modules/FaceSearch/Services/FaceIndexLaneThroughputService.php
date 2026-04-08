<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FaceIndexLaneThroughputService
{
    public function __construct(
        private readonly FaceIndexLaneExecutorInterface $executor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $manifestPath,
        bool $cleanup = true,
    ): array {
        $manifest = $this->loadManifest($manifestPath);
        $assetRoot = $this->resolveAssetRoot($manifest);
        $entries = $this->resolveEntries($manifest, $assetRoot);

        if ($entries === []) {
            throw new RuntimeException('Face-index lane throughput requires at least one manifest entry.');
        }

        $provisioned = $this->provisionBatch($entries);
        $queueName = 'face-index-benchmark-' . Str::lower(Str::random(8));

        try {
            $executorReport = $this->executor->execute(array_keys($provisioned['by_media_id']), $queueName);
            $report = $this->buildReport(
                manifestPath: $manifestPath,
                assetRoot: $assetRoot,
                entries: $entries,
                provisioned: $provisioned,
                executorReport: $executorReport,
            );
        } finally {
            if ($cleanup) {
                $this->cleanupProvisionedBatch($provisioned);
            }
        }

        return $report;
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
            throw new RuntimeException(sprintf('Face-index throughput manifest [%s] does not exist.', $manifestPath));
        }

        $payload = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! isset($payload['entries']) || ! is_array($payload['entries'])) {
            throw new RuntimeException('Face-index throughput manifest is invalid.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function resolveAssetRoot(array $manifest): string
    {
        $envKey = (string) ($manifest['asset_root_env'] ?? '');

        if ($envKey !== '') {
            $fromEnv = env($envKey);

            if (is_string($fromEnv) && trim($fromEnv) !== '') {
                return rtrim($fromEnv, "\\/");
            }
        }

        $fallback = (string) ($manifest['fallback_asset_root'] ?? '');
        $userProfile = getenv('USERPROFILE') ?: '';

        return rtrim(str_replace('%USERPROFILE%', (string) $userProfile, $fallback), "\\/");
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<int, array<string, mixed>>
     */
    private function resolveEntries(array $manifest, string $assetRoot): array
    {
        if (! is_dir($assetRoot)) {
            throw new RuntimeException(sprintf('Face-index throughput asset root [%s] does not exist.', $assetRoot));
        }

        $entries = [];

        foreach ((array) $manifest['entries'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $relativePath = (string) ($entry['relative_path'] ?? '');
            $throughputRelativePath = (string) ($entry['smoke_relative_path'] ?? '');
            $selectedRelativePath = $throughputRelativePath !== '' ? $throughputRelativePath : $relativePath;
            $selectedAbsolutePath = $assetRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $selectedRelativePath);

            if (! File::exists($selectedAbsolutePath)) {
                throw new RuntimeException(sprintf('Face-index throughput asset [%s] does not exist.', $selectedRelativePath));
            }

            $entries[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'event_id' => (string) ($entry['event_id'] ?? 'local-face-index-throughput'),
                'person_id' => (string) ($entry['person_id'] ?? ''),
                'scene_type' => (string) ($entry['scene_type'] ?? 'unknown'),
                'quality_label' => (string) ($entry['quality_label'] ?? 'unknown'),
                'target_face_selection' => is_array($entry['target_face_selection'] ?? null)
                    ? $entry['target_face_selection']
                    : null,
                'expected_positive_set' => array_values(array_map('strval', (array) ($entry['expected_positive_set'] ?? []))),
                'selected_relative_path' => $selectedRelativePath,
                'selected_absolute_path' => $selectedAbsolutePath,
                'selected_size_bytes' => (int) filesize($selectedAbsolutePath),
                'mime_type' => $this->detectMimeType($selectedAbsolutePath),
                'width' => (int) (getimagesize($selectedAbsolutePath)[0] ?? 0),
                'height' => (int) (getimagesize($selectedAbsolutePath)[1] ?? 0),
            ];
        }

        return $entries;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function provisionBatch(array $entries): array
    {
        $organization = Organization::query()->create([
            'trade_name' => 'FaceIndex Throughput',
            'legal_name' => 'FaceIndex Throughput LTDA',
            'slug' => 'face-index-throughput-' . Str::lower(Str::random(8)),
            'type' => 'internal',
            'status' => 'active',
            'email' => 'face-index-throughput@example.test',
            'billing_email' => 'face-index-throughput@example.test',
            'phone' => '0000-0000',
            'timezone' => 'America/Sao_Paulo',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'title' => 'FaceIndex Throughput',
            'slug' => 'face-index-throughput-' . Str::lower(Str::random(10)),
            'event_type' => 'wedding',
            'status' => 'active',
            'visibility' => 'public',
            'moderation_mode' => 'manual',
            'starts_at' => now(),
            'ends_at' => now()->addHours(4),
            'location_name' => 'Benchmark',
            'description' => 'Temporary face-index throughput dataset',
            'retention_days' => 1,
            'commercial_mode' => 'none',
        ]);

        EventFaceSearchSetting::query()->create([
            'event_id' => $event->id,
            'enabled' => true,
            'provider_key' => 'compreface',
            'embedding_model_key' => 'compreface-face-v1',
            'vector_store_key' => 'pgvector',
            'search_strategy' => 'exact',
            'min_face_size_px' => (int) config('face_search.min_face_size_px', 24),
            'min_quality_score' => (float) config('face_search.min_quality_score', 0.60),
            'search_threshold' => (float) config('face_search.search_threshold', 0.50),
                'top_k' => (int) config('face_search.top_k', 50),
                'allow_public_selfie_search' => false,
                'selfie_retention_hours' => 24,
            ]);

        $storageDisk = 'local';
        $storagePrefix = 'benchmarks/face-index-throughput/' . Str::uuid()->toString();
        $byMediaId = [];

        foreach ($entries as $index => $entry) {
            $extension = strtolower((string) pathinfo((string) $entry['selected_relative_path'], PATHINFO_EXTENSION));
            $originalFilename = sprintf('%s.%s', $entry['id'], $extension !== '' ? $extension : 'jpg');
            $storagePath = $storagePrefix . '/' . $originalFilename;

            Storage::disk($storageDisk)->put($storagePath, (string) File::get((string) $entry['selected_absolute_path']));

            $media = EventMedia::query()->create([
                'event_id' => $event->id,
                'media_type' => 'image',
                'source_type' => 'benchmark',
                'source_label' => 'face-index-throughput',
                'original_filename' => $originalFilename,
                'original_disk' => $storageDisk,
                'original_path' => $storagePath,
                'client_filename' => $originalFilename,
                'mime_type' => $entry['mime_type'],
                'size_bytes' => $entry['selected_size_bytes'],
                'width' => $entry['width'],
                'height' => $entry['height'],
                'processing_status' => MediaProcessingStatus::Received->value,
                'moderation_status' => ModerationStatus::Approved->value,
                'publication_status' => PublicationStatus::Draft->value,
                'pipeline_version' => 'face-index-throughput-v1',
                'sort_order' => $index,
            ]);

            $byMediaId[$media->id] = [
                'media_id' => $media->id,
                'entry' => $entry,
                'storage_disk' => $storageDisk,
                'storage_path' => $storagePath,
            ];
        }

        return [
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'storage_prefix' => $storagePrefix,
            'by_media_id' => $byMediaId,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @param array<string, mixed> $provisioned
     * @param array<string, mixed> $executorReport
     * @return array<string, mixed>
     */
    private function buildReport(
        string $manifestPath,
        string $assetRoot,
        array $entries,
        array $provisioned,
        array $executorReport,
    ): array {
        $mediaIds = array_keys($provisioned['by_media_id']);
        $runs = MediaProcessingRun::query()
            ->whereIn('event_media_id', $mediaIds)
            ->where('stage_key', 'face_index')
            ->get()
            ->keyBy('event_media_id');

        $mediaItems = EventMedia::query()
            ->with(['faces', 'processingRuns'])
            ->whereIn('id', $mediaIds)
            ->get()
            ->keyBy('id');

        $wallClockMs = max(1, (int) ($executorReport['wall_clock_ms'] ?? 0));
        $completed = 0;
        $failed = 0;
        $skipped = 0;
        $runDurations = [];
        $sceneRows = [];
        $workerRefs = [];

        foreach ($provisioned['by_media_id'] as $mediaId => $context) {
            $run = $runs->get($mediaId);
            $media = $mediaItems->get($mediaId);
            $entry = $context['entry'];

            $status = (string) ($run?->status ?? 'missing');
            $resultJson = is_array($run?->result_json) ? $run->result_json : [];
            $metricsJson = is_array($run?->metrics_json) ? $run->metrics_json : [];
            $durationMs = $run?->started_at && $run?->finished_at
                ? $run->started_at->diffInMilliseconds($run->finished_at)
                : null;

            if ($status === 'completed') {
                $completed++;
            } elseif ($status === 'failed') {
                $failed++;
            } else {
                $skipped++;
            }

            if ($durationMs !== null) {
                $runDurations[] = $durationMs;
            }

            if (is_string($run?->worker_ref) && $run->worker_ref !== '') {
                $workerRefs[] = $run->worker_ref;
            }

            $sceneRows[] = [
                'id' => $entry['id'],
                'scene_type' => $entry['scene_type'],
                'quality_label' => $entry['quality_label'],
                'target_face_selection' => $entry['target_face_selection'],
                'status' => $status,
                'run_duration_ms' => $durationMs,
                'faces_detected' => (int) ($resultJson['faces_detected'] ?? $metricsJson['faces_detected'] ?? 0),
                'faces_indexed' => (int) ($resultJson['faces_indexed'] ?? $metricsJson['faces_indexed'] ?? 0),
                'indexed_face_rows' => $media?->faces?->count() ?? 0,
            ];
        }

        return [
            'provider' => 'compreface',
            'queue_connection' => (string) config('queue.default'),
            'queue_name' => (string) ($executorReport['queue_name'] ?? 'face-index'),
            'manifest_path' => $manifestPath,
            'asset_root' => $assetRoot,
            'event_id' => $provisioned['event_id'],
            'batch_size' => count($entries),
            'request_outcome' => ((int) ($executorReport['exit_code'] ?? 1) === 0 && $failed === 0) ? 'success' : 'degraded',
            'executor' => $executorReport,
            'summary' => [
                'jobs_completed' => $completed,
                'jobs_failed' => $failed,
                'jobs_skipped_or_missing' => $skipped,
                'wall_clock_ms' => $wallClockMs,
                'throughput_face_index_per_minute' => round(($completed / $wallClockMs) * 60000, 2),
                'p50_run_duration_ms' => $this->percentile($runDurations, 50),
                'p95_run_duration_ms' => $this->percentile($runDurations, 95),
                'worker_refs' => array_values(array_unique($workerRefs)),
            ],
            'scene_type_breakdown' => collect($sceneRows)
                ->groupBy('scene_type')
                ->map(function (Collection $rows, string $sceneType): array {
                    $durations = $rows->pluck('run_duration_ms')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->all();

                    return [
                        'scene_type' => $sceneType,
                        'count' => $rows->count(),
                        'avg_run_duration_ms' => $rows->avg('run_duration_ms') !== null ? round((float) $rows->avg('run_duration_ms'), 2) : null,
                        'p95_run_duration_ms' => $this->percentile($durations, 95),
                        'avg_faces_detected' => round((float) $rows->avg('faces_detected'), 2),
                        'avg_faces_indexed' => round((float) $rows->avg('faces_indexed'), 2),
                        'avg_ms_per_detected_face' => $this->averageMsPerFace($rows, 'faces_detected'),
                        'avg_ms_per_indexed_face' => $this->averageMsPerFace($rows, 'faces_indexed'),
                    ];
                })
                ->values()
                ->all(),
            'slowest_items' => collect($sceneRows)
                ->sortByDesc(fn (array $row): float => (float) ($row['run_duration_ms'] ?? 0.0))
                ->map(function (array $row): array {
                    $detected = max(0, (int) ($row['faces_detected'] ?? 0));
                    $indexed = max(0, (int) ($row['faces_indexed'] ?? 0));
                    $duration = (float) ($row['run_duration_ms'] ?? 0);

                    $row['ms_per_detected_face'] = $detected > 0 ? round($duration / $detected, 2) : null;
                    $row['ms_per_indexed_face'] = $indexed > 0 ? round($duration / $indexed, 2) : null;

                    return $row;
                })
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array<string, mixed> $provisioned
     */
    private function cleanupProvisionedBatch(array $provisioned): void
    {
        foreach ((array) ($provisioned['by_media_id'] ?? []) as $mediaId => $context) {
            $media = EventMedia::query()->with(['faces', 'processingRuns'])->find($mediaId);

            if (! $media) {
                continue;
            }

            foreach ($media->faces as $face) {
                if ($face->crop_disk && $face->crop_path) {
                    Storage::disk($face->crop_disk)->delete($face->crop_path);
                }
            }

            $media->faces()->delete();
            $media->processingRuns()->delete();

            if (is_string($context['storage_disk'] ?? null) && is_string($context['storage_path'] ?? null)) {
                Storage::disk($context['storage_disk'])->delete($context['storage_path']);
            }

            $media->forceDelete();
        }

        EventFaceSearchSetting::query()->where('event_id', $provisioned['event_id'] ?? null)->delete();
        Event::query()->whereKey($provisioned['event_id'] ?? null)->delete();
        Organization::query()->whereKey($provisioned['organization_id'] ?? null)->delete();
    }

    private function detectMimeType(string $path): string
    {
        return File::mimeType($path) ?: 'image/jpeg';
    }

    /**
     * @param array<int, int|float> $values
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

    private function averageMsPerFace(Collection $rows, string $faceCountField): ?float
    {
        $totalDuration = 0.0;
        $totalFaces = 0;

        foreach ($rows as $row) {
            $duration = (float) ($row['run_duration_ms'] ?? 0);
            $faceCount = (int) ($row[$faceCountField] ?? 0);

            if ($duration <= 0 || $faceCount <= 0) {
                continue;
            }

            $totalDuration += $duration;
            $totalFaces += $faceCount;
        }

        if ($totalFaces === 0) {
            return null;
        }

        return round($totalDuration / $totalFaces, 2);
    }
}
