<?php

namespace App\Modules\FaceSearch\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class CompreFaceSmokeService
{
    public function __construct(
        private readonly CompreFaceClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $manifestPath, bool $dryRun = false): array
    {
        $manifest = $this->loadManifest($manifestPath);
        $assetRoot = $this->resolveAssetRoot($manifest);

        if (! is_dir($assetRoot)) {
            throw new RuntimeException(sprintf(
                'Local dataset asset root [%s] does not exist. Configure %s or provide a valid fallback path.',
                $assetRoot,
                (string) ($manifest['asset_root_env'] ?? 'the manifest asset_root_env'),
            ));
        }

        $entries = $this->resolveEntries($manifest, $assetRoot);
        $report = $this->baseReport($manifestPath, $assetRoot, $entries, $dryRun);

        if ($dryRun) {
            $report['request_outcome'] = 'success';

            return $report;
        }

        $detections = [];

        foreach ($entries as $entry) {
            try {
                $detections[] = $this->runDetectionProbe($entry);
            } catch (Throwable $exception) {
                $detections[] = $this->failedDetectionProbe($entry, $exception);
            }
        }

        $report['detections'] = $detections;
        $report['verification_checks'] = $this->runVerificationChecks(collect($detections));
        $report['request_outcome'] = collect($detections)->contains(fn (array $probe): bool => ($probe['request_outcome'] ?? null) !== 'success')
            ? 'degraded'
            : 'success';

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
            throw new RuntimeException(sprintf('CompreFace smoke manifest [%s] does not exist.', $manifestPath));
        }

        $payload = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! isset($payload['entries']) || ! is_array($payload['entries'])) {
            throw new RuntimeException('CompreFace smoke manifest is invalid.');
        }

        $payload['_resolved_path'] = $resolvedPath;

        return $payload;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<int, array<string, mixed>>
     */
    private function resolveEntries(array $manifest, string $assetRoot): array
    {
        $entries = [];

        foreach ((array) $manifest['entries'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $relativePath = (string) ($entry['relative_path'] ?? '');
            $smokeRelativePath = (string) ($entry['smoke_relative_path'] ?? '');
            $selectedRelativePath = $smokeRelativePath !== '' ? $smokeRelativePath : $relativePath;
            $selectedAbsolutePath = $assetRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $selectedRelativePath);

            if (! File::exists($selectedAbsolutePath)) {
                throw new RuntimeException(sprintf(
                    'CompreFace smoke asset [%s] does not exist.',
                    $selectedRelativePath,
                ));
            }

            $entries[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'event_id' => (string) ($entry['event_id'] ?? ''),
                'person_id' => (string) ($entry['person_id'] ?? ''),
                'quality_label' => (string) ($entry['quality_label'] ?? 'unknown'),
                'scene_type' => (string) ($entry['scene_type'] ?? 'unknown'),
                'expected_positive_set' => array_values(array_map('strval', (array) ($entry['expected_positive_set'] ?? []))),
                'expected_moderation_bucket' => (string) ($entry['expected_moderation_bucket'] ?? 'unknown'),
                'consent_basis' => (string) ($entry['consent_basis'] ?? ''),
                'target_face_selection' => is_array($entry['target_face_selection'] ?? null)
                    ? $entry['target_face_selection']
                    : ['strategy' => 'largest', 'value' => 1],
                'source_relative_path' => $relativePath,
                'selected_relative_path' => $selectedRelativePath,
                'selected_absolute_path' => $selectedAbsolutePath,
                'path_used' => 'base64',
                'requires_derivative_for_compreface' => (bool) ($entry['requires_derivative_for_compreface'] ?? false),
                'uses_derivative' => $smokeRelativePath !== '',
                'selected_size_bytes' => (int) filesize($selectedAbsolutePath),
            ];
        }

        return $entries;
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
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function baseReport(string $manifestPath, string $assetRoot, array $entries, bool $dryRun): array
    {
        return [
            'provider' => 'compreface',
            'model' => (string) config('face_search.providers.compreface.model', 'compreface-face-v1'),
            'provider_version' => (string) config('face_search.providers.compreface.provider_version', 'compreface-rest-v1'),
            'threshold_tested' => (float) config('face_search.search_threshold', 0.50),
            'fallback_triggered' => false,
            'manifest_path' => $manifestPath,
            'asset_root' => $assetRoot,
            'dry_run' => $dryRun,
            'entries' => array_map(static fn (array $entry): array => [
                'id' => $entry['id'],
                'person_id' => $entry['person_id'],
                'selected_relative_path' => $entry['selected_relative_path'],
                'selected_size_bytes' => $entry['selected_size_bytes'],
                'uses_derivative' => $entry['uses_derivative'],
                'path_used' => $entry['path_used'],
                'scene_type' => $entry['scene_type'],
                'target_face_selection' => $entry['target_face_selection'],
            ], $entries),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function runDetectionProbe(array $entry): array
    {
        $startedAt = microtime(true);
        $payload = $this->client->detectBase64(
            base64_encode((string) File::get($entry['selected_absolute_path'])),
            [
                'face_plugins' => 'calculator,landmarks',
                'status' => true,
                'limit' => 0,
            ],
        );
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $result = $payload['result'] ?? [];

        if (! is_array($result) || $result === []) {
            throw new RuntimeException(sprintf('CompreFace smoke detection returned no face for [%s].', $entry['id']));
        }

        $face = $this->selectTargetFace($result, $entry);
        $embedding = array_values(array_map(
            static fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0,
            (array) ($face['embedding'] ?? []),
        ));

        if ($embedding === []) {
            throw new RuntimeException(sprintf('CompreFace smoke detection returned no embedding for [%s].', $entry['id']));
        }

        return [
            'id' => $entry['id'],
            'event_id' => $entry['event_id'],
            'person_id' => $entry['person_id'],
            'quality_label' => $entry['quality_label'],
            'scene_type' => $entry['scene_type'],
            'expected_positive_set' => $entry['expected_positive_set'],
            'selected_relative_path' => $entry['selected_relative_path'],
            'path_used' => $entry['path_used'],
            'latency_ms' => $latencyMs,
            'embedding_dimension' => count($embedding),
            'detected_faces_count' => count($result),
            'selected_face_strategy' => (string) data_get($entry, 'target_face_selection.strategy', 'largest'),
            'selected_face_value' => (int) data_get($entry, 'target_face_selection.value', 1),
            'selected_face_bbox' => is_array($face['box'] ?? null) ? $face['box'] : null,
            'detector_latency_ms' => is_numeric(data_get($face, 'execution_time.detector'))
                ? (float) data_get($face, 'execution_time.detector')
                : null,
            'calculator_latency_ms' => is_numeric(data_get($face, 'execution_time.calculator'))
                ? (float) data_get($face, 'execution_time.calculator')
                : null,
            'plugins_versions' => is_array($payload['plugins_versions'] ?? null) ? $payload['plugins_versions'] : [],
            'request_outcome' => 'success',
            'embedding' => $embedding,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function failedDetectionProbe(array $entry, Throwable $exception): array
    {
        return [
            'id' => $entry['id'],
            'event_id' => $entry['event_id'],
            'person_id' => $entry['person_id'],
            'quality_label' => $entry['quality_label'],
            'scene_type' => $entry['scene_type'],
            'expected_positive_set' => $entry['expected_positive_set'],
            'selected_relative_path' => $entry['selected_relative_path'],
            'path_used' => $entry['path_used'],
            'latency_ms' => null,
            'embedding_dimension' => 0,
            'detected_faces_count' => 0,
            'selected_face_strategy' => (string) data_get($entry, 'target_face_selection.strategy', 'largest'),
            'selected_face_value' => (int) data_get($entry, 'target_face_selection.value', 1),
            'selected_face_bbox' => null,
            'detector_latency_ms' => null,
            'calculator_latency_ms' => null,
            'plugins_versions' => [],
            'request_outcome' => 'failed',
            'error_message' => $exception->getMessage(),
            'embedding' => [],
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $detections
     * @return array<int, array<string, mixed>>
     */
    private function runVerificationChecks(Collection $detections): array
    {
        if ($detections->contains(fn (array $probe): bool => ($probe['request_outcome'] ?? null) !== 'success')) {
            return [];
        }

        $checks = [];
        $successful = $detections->filter(fn (array $probe): bool => ($probe['request_outcome'] ?? null) === 'success');
        $byPerson = $successful->groupBy('person_id');

        foreach ($byPerson as $personId => $probes) {
            if ($probes->count() < 2) {
                continue;
            }

            $source = $probes->values()->get(0);
            $target = $probes->values()->get(1);
            $checks[] = $this->runVerificationProbe(
                label: 'positive',
                source: $source,
                targets: [$target],
                metadata: ['person_id' => $personId],
            );
        }

        $distinctPeople = $byPerson->keys()->values();

        if ($distinctPeople->count() >= 2) {
            $sourcePerson = (string) $distinctPeople->get(0);
            $targetPerson = (string) $distinctPeople->get(1);
            $source = $byPerson->get($sourcePerson)?->values()->get(0);
            $target = $byPerson->get($targetPerson)?->values()->get(0);

            if (is_array($source) && is_array($target)) {
                $checks[] = $this->runVerificationProbe(
                    label: 'negative',
                    source: $source,
                    targets: [$target],
                    metadata: [
                        'source_person_id' => $sourcePerson,
                        'target_person_id' => $targetPerson,
                    ],
                );
            }
        }

        return $checks;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, array<string, mixed>> $targets
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function runVerificationProbe(string $label, array $source, array $targets, array $metadata): array
    {
        $startedAt = microtime(true);
        $payload = $this->client->verifyEmbeddings(
            sourceEmbedding: (array) $source['embedding'],
            targetEmbeddings: array_map(
                static fn (array $probe): array => array_values((array) ($probe['embedding'] ?? [])),
                $targets,
            ),
        );
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $result = $payload['result'] ?? [];
        $first = is_array($result) && isset($result[0]) && is_array($result[0]) ? $result[0] : [];

        return [
            'label' => $label,
            'similarity' => is_numeric($first['similarity'] ?? null) ? (float) $first['similarity'] : null,
            'latency_ms' => $latencyMs,
            'request_outcome' => 'success',
            ...$metadata,
        ];
    }

    /**
     * @param array<int, mixed> $faces
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function selectTargetFace(array $faces, array $entry): array
    {
        $normalizedFaces = collect($faces)
            ->filter(fn (mixed $face): bool => is_array($face))
            ->map(fn (array $face): array => $face)
            ->values();

        if ($normalizedFaces->isEmpty()) {
            throw new RuntimeException(sprintf('CompreFace smoke detection returned no mappable face for [%s].', $entry['id']));
        }

        $strategy = strtolower((string) data_get($entry, 'target_face_selection.strategy', 'largest'));
        $value = max(1, (int) data_get($entry, 'target_face_selection.value', 1));

        $ordered = match ($strategy) {
            'left_to_right_index' => $normalizedFaces
                ->sort(function (array $left, array $right): int {
                    $leftX = (float) data_get($left, 'box.x_min', INF);
                    $rightX = (float) data_get($right, 'box.x_min', INF);

                    if ($leftX !== $rightX) {
                        return $leftX <=> $rightX;
                    }

                    return (float) data_get($left, 'box.y_min', INF) <=> (float) data_get($right, 'box.y_min', INF);
                })
                ->values(),
            default => $normalizedFaces
                ->sortByDesc(fn (array $face): float => $this->faceArea($face))
                ->values(),
        };

        $selected = $ordered->get($value - 1);

        if (! is_array($selected)) {
            throw new RuntimeException(sprintf(
                'CompreFace smoke selection [%s:%d] could not resolve a target face for [%s].',
                $strategy,
                $value,
                $entry['id'],
            ));
        }

        return $selected;
    }

    /**
     * @param array<string, mixed> $face
     */
    private function faceArea(array $face): float
    {
        $width = max(0.0, (float) data_get($face, 'box.x_max', 0.0) - (float) data_get($face, 'box.x_min', 0.0));
        $height = max(0.0, (float) data_get($face, 'box.y_max', 0.0) - (float) data_get($face, 'box.y_min', 0.0));

        return $width * $height;
    }
}
