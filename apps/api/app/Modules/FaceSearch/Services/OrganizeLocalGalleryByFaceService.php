<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;

class OrganizeLocalGalleryByFaceService
{
    public function __construct(
        private readonly FaceDetectionProviderInterface $detector,
        private readonly FaceEmbeddingProviderInterface $embedder,
        private readonly FaceQualityGateService $qualityGate,
    ) {}

    /**
     * @param array<int, string> $extensions
     * @return array<string, mixed>
     */
    public function run(
        string $inputDirectory,
        string $outputDirectory = '',
        string $noFaceDirectory = '',
        string $providerKey = 'compreface',
        float $clusterThreshold = 0.35,
        int $minFaceSizePx = 24,
        float $minQualityScore = 0.6,
        int $maxDimension = 2560,
        int $maxWorkingBytes = 5_242_880,
        int $limit = 0,
        array $extensions = ['jpg', 'jpeg', 'png', 'webp'],
    ): array {
        $resolvedInputDirectory = $this->resolveRequiredPath($inputDirectory, '%USERPROFILE%/Desktop/ddddd/FINAL');

        if (! is_dir($resolvedInputDirectory)) {
            throw new RuntimeException(sprintf('Local gallery input directory [%s] does not exist.', $resolvedInputDirectory));
        }

        $resolvedNoFaceDirectory = $noFaceDirectory !== ''
            ? $this->resolvePath($noFaceDirectory)
            : $resolvedInputDirectory;
        $resolvedOutputDirectory = $outputDirectory !== ''
            ? $this->resolvePath($outputDirectory)
            : dirname($resolvedInputDirectory) . DIRECTORY_SEPARATOR . 'AGRUPADO_POR_PESSOA' . DIRECTORY_SEPARATOR . now()->format('Ymd-His');

        File::ensureDirectoryExists($resolvedOutputDirectory);

        $normalizedExtensions = $this->normalizeExtensions($extensions);

        if ($normalizedExtensions === []) {
            throw new RuntimeException('At least one valid image extension is required.');
        }

        $settings = new EventFaceSearchSetting(array_merge(
            EventFaceSearchSetting::defaultAttributes(),
            [
                'enabled' => true,
                'provider_key' => $providerKey,
                'min_face_size_px' => max(1, $minFaceSizePx),
                'min_quality_score' => max(0.0, min(1.0, $minQualityScore)),
            ],
        ));

        $images = $this->collectImages($resolvedInputDirectory, $normalizedExtensions, $limit);

        if ($images->isEmpty()) {
            throw new RuntimeException(sprintf('No images found in [%s] for the given extension filter.', $resolvedInputDirectory));
        }

        $imageReports = [];
        $faces = [];
        $faceId = 1;

        foreach ($images as $imagePath) {
            $imageReports[] = $this->processImage(
                imagePath: $imagePath,
                inputDirectory: $resolvedInputDirectory,
                settings: $settings,
                maxDimension: max(256, $maxDimension),
                maxWorkingBytes: max(128 * 1024, $maxWorkingBytes),
                acceptedFaces: $faces,
                nextFaceId: $faceId,
            );
        }

        $clusters = $this->clusterFaces($faces, max(0.01, min(1.0, $clusterThreshold)));
        $materialized = $this->materializeClusters(
            outputDirectory: $resolvedOutputDirectory,
            faces: $faces,
            imageReports: $imageReports,
            inputDirectory: $resolvedInputDirectory,
            clusters: $clusters,
        );

        $report = [
            'input_dir' => $resolvedInputDirectory,
            'output_dir' => $resolvedOutputDirectory,
            'no_face_dir' => $resolvedNoFaceDirectory,
            'provider_key' => $providerKey,
            'cluster_threshold' => round(max(0.01, min(1.0, $clusterThreshold)), 4),
            'settings_used' => [
                'min_face_size_px' => $settings->min_face_size_px,
                'min_quality_score' => $settings->min_quality_score,
                'search_threshold' => $settings->search_threshold,
                'search_strategy' => $settings->search_strategy,
            ],
            'working_image_policy' => [
                'max_dimension' => max(256, $maxDimension),
                'max_working_bytes' => max(128 * 1024, $maxWorkingBytes),
            ],
            'summary' => $this->buildSummary($images, $imageReports, $faces, $materialized),
            'clusters' => $materialized['clusters'],
            'images' => $imageReports,
            'request_outcome' => collect($imageReports)->contains(fn (array $report): bool => in_array((string) ($report['status'] ?? ''), ['failed', 'invalid'], true))
                ? 'degraded'
                : 'success',
        ];

        File::put(
            $resolvedOutputDirectory . DIRECTORY_SEPARATOR . 'report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        return [
            'input_dir' => $resolvedInputDirectory,
            'output_dir' => $resolvedOutputDirectory,
            'no_face_dir' => $resolvedNoFaceDirectory,
            'summary' => $report['summary'],
            'top_clusters' => array_slice($materialized['clusters'], 0, 20),
            'report_path' => $resolvedOutputDirectory . DIRECTORY_SEPARATOR . 'report.json',
            'request_outcome' => $report['request_outcome'],
        ];
    }

    /**
     * @param array<int, string> $extensions
     * @return array<int, string>
     */
    private function normalizeExtensions(array $extensions): array
    {
        return collect($extensions)
            ->map(function (mixed $extension): array {
                if (is_string($extension)) {
                    return explode(',', $extension);
                }

                return [(string) $extension];
            })
            ->flatten()
            ->map(fn (mixed $extension): string => strtolower(ltrim(trim((string) $extension), '.')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $extensions
     * @return Collection<int, string>
     */
    private function collectImages(string $inputDirectory, array $extensions, int $limit): Collection
    {
        $files = collect(File::allFiles($inputDirectory))
            ->filter(fn (\SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), $extensions, true))
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->sort()
            ->values();

        return $limit > 0 ? $files->take($limit)->values() : $files;
    }

    /**
     * @param array<int, array<string, mixed>> $acceptedFaces
     * @param int $nextFaceId
     * @return array<string, mixed>
     */
    private function processImage(
        string $imagePath,
        string $inputDirectory,
        EventFaceSearchSetting $settings,
        int $maxDimension,
        int $maxWorkingBytes,
        array &$acceptedFaces,
        int &$nextFaceId,
    ): array {
        $relativePath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, str_replace($inputDirectory, '', $imagePath)), DIRECTORY_SEPARATOR);
        $sizeBytes = (int) (filesize($imagePath) ?: 0);
        $baseReport = [
            'relative_path' => str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
            'absolute_path' => $imagePath,
            'size_bytes' => $sizeBytes,
        ];
        $usesWorkingDerivative = false;

        try {
            $originalBinary = (string) File::get($imagePath);

            if ($originalBinary === '') {
                return [
                    ...$baseReport,
                    'status' => 'invalid',
                    'faces_detected' => 0,
                    'faces_accepted' => 0,
                    'faces_rejected' => 0,
                    'person_cluster_ids' => [],
                    'uses_working_derivative' => false,
                    'error' => 'empty_image_binary',
                ];
            }

            [$workingBinary, $usesWorkingDerivative] = $this->prepareWorkingBinary($originalBinary, $maxDimension, $maxWorkingBytes);
            $media = new EventMedia([
                'media_type' => 'image',
                'mime_type' => File::mimeType($imagePath) ?: 'image/jpeg',
                'original_filename' => basename($imagePath),
                'source_type' => 'local-face-organizer',
            ]);

            $faces = array_values($this->detector->detect($media, $settings, $workingBinary));
            $acceptedIds = [];
            $rejectedCount = 0;

            foreach ($faces as $face) {
                $assessment = $this->qualityGate->assess($face, $settings);

                if ($assessment->isRejected()) {
                    $rejectedCount++;
                    continue;
                }

                $cropBinary = $this->cropFace($workingBinary, $face);
                $embedding = $this->embedder->embed($media, $settings, $cropBinary, $face);
                $faceMinSide = min($face->boundingBox->width, $face->boundingBox->height);
                $currentFaceId = $nextFaceId++;

                $acceptedFaces[] = [
                    'face_id' => $currentFaceId,
                    'image_path' => $imagePath,
                    'relative_path' => str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
                    'vector' => $embedding->vector,
                    'quality_score' => $face->qualityScore,
                    'detection_confidence' => $face->detectionConfidence,
                    'face_min_side_px' => $faceMinSide,
                    'quality_tier' => $assessment->tier->value,
                ];
                $acceptedIds[] = $currentFaceId;
            }

            return [
                ...$baseReport,
                'status' => count($acceptedIds) > 0 ? 'clustered' : (count($faces) > 0 ? 'rejected_only' : 'no_face'),
                'faces_detected' => count($faces),
                'faces_accepted' => count($acceptedIds),
                'faces_rejected' => $rejectedCount,
                'accepted_face_ids' => $acceptedIds,
                'person_cluster_ids' => [],
                'uses_working_derivative' => $usesWorkingDerivative,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            if ($this->isNoFaceProviderResponse($exception)) {
                return [
                    ...$baseReport,
                    'status' => 'no_face',
                    'faces_detected' => 0,
                    'faces_accepted' => 0,
                    'faces_rejected' => 0,
                    'accepted_face_ids' => [],
                    'person_cluster_ids' => [],
                    'uses_working_derivative' => $usesWorkingDerivative,
                    'error' => null,
                ];
            }

            return [
                ...$baseReport,
                'status' => 'failed',
                'faces_detected' => 0,
                'faces_accepted' => 0,
                'faces_rejected' => 0,
                'accepted_face_ids' => [],
                'person_cluster_ids' => [],
                'uses_working_derivative' => $usesWorkingDerivative,
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function prepareWorkingBinary(string $binary, int $maxDimension, int $maxWorkingBytes): array
    {
        $image = Image::decode($binary);
        $needsScale = $image->width() > $maxDimension || $image->height() > $maxDimension || strlen($binary) > $maxWorkingBytes;

        if (! $needsScale) {
            return [$binary, false];
        }

        $candidate = $image->scaleDown(width: $maxDimension, height: $maxDimension);
        $qualities = [82, 72, 62, 52];

        foreach ($qualities as $quality) {
            $encoded = (string) $candidate->encodeUsingMediaType('image/jpeg', $quality);

            if (strlen($encoded) <= $maxWorkingBytes || $quality === $qualities[array_key_last($qualities)]) {
                return [$encoded, true];
            }
        }

        return [$binary, false];
    }

    private function cropFace(string $binary, DetectedFaceData $face): string
    {
        $cropped = Image::decode($binary)->crop(
            $face->boundingBox->width,
            $face->boundingBox->height,
            $face->boundingBox->x,
            $face->boundingBox->y,
        );

        return (string) $cropped->encodeUsingMediaType('image/webp', 88);
    }

    /**
     * @param array<int, array<string, mixed>> $faces
     * @return array{
     *   face_to_cluster: array<int, int>,
     *   clusters: array<int, array<string, mixed>>
     * }
     */
    private function clusterFaces(array $faces, float $clusterThreshold): array
    {
        $sortedFaces = collect($faces)
            ->sort(function (array $left, array $right): int {
                $size = ((float) ($right['face_min_side_px'] ?? 0.0)) <=> ((float) ($left['face_min_side_px'] ?? 0.0));

                if ($size !== 0) {
                    return $size;
                }

                $quality = ((float) ($right['quality_score'] ?? 0.0)) <=> ((float) ($left['quality_score'] ?? 0.0));

                if ($quality !== 0) {
                    return $quality;
                }

                return strcmp((string) ($left['relative_path'] ?? ''), (string) ($right['relative_path'] ?? ''));
            })
            ->values()
            ->all();

        $clusters = [];
        $faceToCluster = [];
        $nextClusterId = 1;

        foreach ($sortedFaces as $face) {
            $bestClusterId = null;
            $bestDistance = INF;

            foreach ($clusters as $clusterId => $cluster) {
                $distance = $this->minDistanceToCluster((array) $face['vector'], (array) ($cluster['exemplars'] ?? []));

                if ($distance <= $clusterThreshold && $distance < $bestDistance) {
                    $bestClusterId = $clusterId;
                    $bestDistance = $distance;
                }
            }

            if ($bestClusterId === null) {
                $bestClusterId = $nextClusterId++;
                $clusters[$bestClusterId] = [
                    'cluster_id' => $bestClusterId,
                    'face_ids' => [],
                    'image_paths' => [],
                    'relative_paths' => [],
                    'exemplars' => [],
                    'best_face_side_px' => 0.0,
                    'best_quality_score' => 0.0,
                    'sample_relative_path' => null,
                ];
            }

            $faceToCluster[(int) $face['face_id']] = $bestClusterId;
            $clusters[$bestClusterId]['face_ids'][] = (int) $face['face_id'];
            $clusters[$bestClusterId]['image_paths'][] = (string) $face['image_path'];
            $clusters[$bestClusterId]['relative_paths'][] = (string) $face['relative_path'];

            $clusters[$bestClusterId] = $this->updateClusterExemplars($clusters[$bestClusterId], $face);
        }

        return [
            'face_to_cluster' => $faceToCluster,
            'clusters' => $clusters,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $exemplars
     * @param array<int, float> $vector
     */
    private function minDistanceToCluster(array $vector, array $exemplars): float
    {
        $distances = collect($exemplars)
            ->map(fn (array $exemplar): float => $this->cosineDistance($vector, (array) ($exemplar['vector'] ?? [])))
            ->values()
            ->all();

        if ($distances === []) {
            return INF;
        }

        return min($distances);
    }

    /**
     * @param array<string, mixed> $cluster
     * @param array<string, mixed> $face
     * @return array<string, mixed>
     */
    private function updateClusterExemplars(array $cluster, array $face): array
    {
        $cluster['best_face_side_px'] = max((float) ($cluster['best_face_side_px'] ?? 0.0), (float) ($face['face_min_side_px'] ?? 0.0));
        $cluster['best_quality_score'] = max((float) ($cluster['best_quality_score'] ?? 0.0), (float) ($face['quality_score'] ?? 0.0));

        if ($cluster['sample_relative_path'] === null || (float) ($face['face_min_side_px'] ?? 0.0) >= (float) ($cluster['best_face_side_px'] ?? 0.0)) {
            $cluster['sample_relative_path'] = (string) ($face['relative_path'] ?? '');
        }

        $cluster['exemplars'][] = [
            'face_id' => (int) $face['face_id'],
            'vector' => (array) $face['vector'],
            'quality_score' => (float) ($face['quality_score'] ?? 0.0),
            'face_min_side_px' => (float) ($face['face_min_side_px'] ?? 0.0),
        ];

        usort($cluster['exemplars'], function (array $left, array $right): int {
            $size = ($right['face_min_side_px'] ?? 0.0) <=> ($left['face_min_side_px'] ?? 0.0);

            if ($size !== 0) {
                return $size;
            }

            return ($right['quality_score'] ?? 0.0) <=> ($left['quality_score'] ?? 0.0);
        });

        $cluster['exemplars'] = array_slice($cluster['exemplars'], 0, 8);

        return $cluster;
    }

    /**
     * @param array<int, array<string, mixed>> $faces
     * @param array<int, array<string, mixed>> $imageReports
     * @param array{face_to_cluster: array<int, int>, clusters: array<int, array<string, mixed>>} $clusters
     * @return array{clusters: array<int, array<string, mixed>>}
     */
    private function materializeClusters(
        string $outputDirectory,
        array $faces,
        array &$imageReports,
        string $inputDirectory,
        array $clusters,
    ): array {
        $faceById = collect($faces)->keyBy(fn (array $face): int => (int) $face['face_id']);
        $clusterList = collect($clusters['clusters'])
            ->map(function (array $cluster): array {
                $uniqueImages = collect((array) ($cluster['relative_paths'] ?? []))->unique()->values()->all();

                return [
                    ...$cluster,
                    'unique_image_count' => count($uniqueImages),
                    'face_count' => count((array) ($cluster['face_ids'] ?? [])),
                    'relative_paths' => $uniqueImages,
                ];
            })
            ->sort(function (array $left, array $right): int {
                $images = ((int) ($right['unique_image_count'] ?? 0)) <=> ((int) ($left['unique_image_count'] ?? 0));

                if ($images !== 0) {
                    return $images;
                }

                $facesCount = ((int) ($right['face_count'] ?? 0)) <=> ((int) ($left['face_count'] ?? 0));

                if ($facesCount !== 0) {
                    return $facesCount;
                }

                return ((int) ($left['cluster_id'] ?? 0)) <=> ((int) ($right['cluster_id'] ?? 0));
            })
            ->values()
            ->all();

        $clusterFolderNames = [];
        $materializedClusters = [];

        foreach ($clusterList as $index => $cluster) {
            $folderName = sprintf('pessoa-%03d', $index + 1);
            $clusterFolderNames[(int) $cluster['cluster_id']] = $folderName;
            $clusterDirectory = $outputDirectory . DIRECTORY_SEPARATOR . $folderName;

            File::ensureDirectoryExists($clusterDirectory);

            foreach ((array) ($cluster['relative_paths'] ?? []) as $relativePath) {
                $sourcePath = $inputDirectory . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $relativePath);
                $destinationPath = $clusterDirectory . DIRECTORY_SEPARATOR . basename((string) $relativePath);

                if (File::exists($sourcePath) && ! File::exists($destinationPath)) {
                    $this->materializeImageFile($sourcePath, $destinationPath);
                }
            }

            $materializedClusters[] = [
                'cluster_id' => (int) $cluster['cluster_id'],
                'folder' => $folderName,
                'unique_image_count' => (int) ($cluster['unique_image_count'] ?? 0),
                'face_count' => (int) ($cluster['face_count'] ?? 0),
                'sample_relative_path' => $cluster['sample_relative_path'],
                'best_face_side_px' => round((float) ($cluster['best_face_side_px'] ?? 0.0), 2),
                'best_quality_score' => round((float) ($cluster['best_quality_score'] ?? 0.0), 4),
            ];
        }

        foreach ($imageReports as &$report) {
            $clusterIds = collect((array) ($report['accepted_face_ids'] ?? []))
                ->map(fn (int $faceId): ?int => $clusters['face_to_cluster'][$faceId] ?? null)
                ->filter(fn (?int $clusterId): bool => $clusterId !== null)
                ->map(fn (int $clusterId): string => $clusterFolderNames[$clusterId] ?? sprintf('cluster-%d', $clusterId))
                ->unique()
                ->values()
                ->all();

            $report['person_cluster_ids'] = $clusterIds;
        }

        unset($report);

        return [
            'clusters' => $materializedClusters,
        ];
    }

    private function materializeImageFile(string $sourcePath, string $destinationPath): void
    {
        if ($this->canUseHardLink($sourcePath, $destinationPath) && @link($sourcePath, $destinationPath)) {
            return;
        }

        File::copy($sourcePath, $destinationPath);
    }

    private function canUseHardLink(string $sourcePath, string $destinationPath): bool
    {
        $sourceVolume = strtoupper((string) substr($sourcePath, 0, 2));
        $destinationVolume = strtoupper((string) substr($destinationPath, 0, 2));

        return $sourceVolume !== '' && $sourceVolume === $destinationVolume;
    }

    /**
     * @param Collection<int, string> $images
     * @param array<int, array<string, mixed>> $imageReports
     * @param array<int, array<string, mixed>> $faces
     * @param array{clusters: array<int, array<string, mixed>>} $materialized
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $images, array $imageReports, array $faces, array $materialized): array
    {
        $reports = collect($imageReports);

        return [
            'images_total' => $images->count(),
            'images_clustered' => $reports->where('status', 'clustered')->count(),
            'images_rejected_only' => $reports->where('status', 'rejected_only')->count(),
            'images_without_faces' => $reports->where('status', 'no_face')->count(),
            'images_failed_or_invalid' => $reports->filter(fn (array $report): bool => in_array((string) ($report['status'] ?? ''), ['failed', 'invalid'], true))->count(),
            'faces_accepted_total' => count($faces),
            'clusters_total' => count($materialized['clusters']),
            'top_cluster_image_count' => collect($materialized['clusters'])->max('unique_image_count'),
            'top_cluster_face_count' => collect($materialized['clusters'])->max('face_count'),
        ];
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     */
    private function cosineDistance(array $left, array $right): float
    {
        $count = min(count($left), count($right));

        if ($count === 0) {
            return 1.0;
        }

        $dot = 0.0;
        $normLeft = 0.0;
        $normRight = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $dot += $left[$index] * $right[$index];
            $normLeft += $left[$index] ** 2;
            $normRight += $right[$index] ** 2;
        }

        if ($normLeft <= 0.0 || $normRight <= 0.0) {
            return 1.0;
        }

        $similarity = $dot / (sqrt($normLeft) * sqrt($normRight));

        return 1.0 - max(-1.0, min(1.0, $similarity));
    }

    private function isNoFaceProviderResponse(\Throwable $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'no face is found');
    }

    private function resolveRequiredPath(string $path, string $fallback): string
    {
        return $this->resolvePath($path !== '' ? $path : $fallback);
    }

    private function resolvePath(string $path): string
    {
        $userProfile = getenv('USERPROFILE') ?: '';

        return str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            str_replace('%USERPROFILE%', (string) $userProfile, $path),
        );
    }
}
