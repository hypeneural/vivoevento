<?php

namespace App\Modules\FaceSearch\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class CaltechWebFacesLocalLoaderService
{
    /**
     * @return array<string, mixed>
     */
    public function run(
        string $datasetRoot = '',
        string $groundTruthPath = '',
        string $selection = 'sequential',
        int $limit = 100,
        string $outputDirectory = '',
        string $pythonBinary = 'python',
    ): array {
        $resolvedDatasetRoot = $this->resolveDatasetRoot($datasetRoot);
        $resolvedGroundTruthPath = $this->resolveGroundTruthPath($groundTruthPath);
        $normalizedSelection = $this->normalizeSelection($selection);
        $resolvedOutputDirectory = $this->resolveOutputDirectory($outputDirectory);
        $scriptPath = base_path('app/Modules/FaceSearch/Support/export_caltech_webfaces.py');

        if (! is_dir($resolvedDatasetRoot)) {
            throw new RuntimeException(sprintf('Caltech WebFaces dataset root [%s] does not exist.', $resolvedDatasetRoot));
        }

        if (! File::exists($resolvedGroundTruthPath)) {
            throw new RuntimeException(sprintf('Caltech WebFaces ground truth [%s] does not exist.', $resolvedGroundTruthPath));
        }

        if (! File::exists($scriptPath)) {
            throw new RuntimeException(sprintf('Caltech WebFaces exporter script [%s] does not exist.', $scriptPath));
        }

        File::ensureDirectoryExists($resolvedOutputDirectory);

        $result = Process::path(base_path())
            ->timeout(1800)
            ->run([
                $pythonBinary !== '' ? $pythonBinary : 'python',
                $scriptPath,
                '--dataset-root',
                $resolvedDatasetRoot,
                '--ground-truth',
                $resolvedGroundTruthPath,
                '--selection',
                $normalizedSelection,
                '--limit',
                (string) max(1, $limit),
                '--output-dir',
                $resolvedOutputDirectory,
            ]);

        if (! $result->successful()) {
            throw new RuntimeException(sprintf(
                'Caltech WebFaces loader failed with exit code [%s]. %s',
                (string) ($result->exitCode() ?? 'unknown'),
                trim($result->output() . PHP_EOL . $result->errorOutput()),
            ));
        }

        try {
            $payload = json_decode((string) $result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Caltech WebFaces loader returned invalid JSON.', previous: $exception);
        }

        if (! is_array($payload) || ! is_string($payload['manifest_path'] ?? null)) {
            throw new RuntimeException('Caltech WebFaces loader did not return a valid manifest payload.');
        }

        if (! File::exists((string) $payload['manifest_path'])) {
            throw new RuntimeException(sprintf(
                'Caltech WebFaces loader reported manifest [%s], but the file does not exist.',
                (string) $payload['manifest_path'],
            ));
        }

        $payload['dataset_root'] = $resolvedDatasetRoot;
        $payload['ground_truth_path'] = $resolvedGroundTruthPath;
        $payload['selection'] = $normalizedSelection;
        $payload['output_dir'] = $resolvedOutputDirectory;

        return $payload;
    }

    private function normalizeSelection(string $selection): string
    {
        $selection = strtolower(trim($selection));

        return in_array($selection, ['sequential', 'smallest_annotated_faces', 'multi_face_density'], true)
            ? $selection
            : 'sequential';
    }

    private function resolveDatasetRoot(string $datasetRoot): string
    {
        if (trim($datasetRoot) !== '') {
            return $this->resolvePath($datasetRoot);
        }

        return $this->resolvePath('%USERPROFILE%/Desktop/model/extracted/caltech_webfaces');
    }

    private function resolveGroundTruthPath(string $groundTruthPath): string
    {
        if (trim($groundTruthPath) !== '') {
            return $this->resolvePath($groundTruthPath);
        }

        return $this->resolvePath('%USERPROFILE%/Desktop/model/WebFaces_GroundThruth.txt');
    }

    private function resolveOutputDirectory(string $outputDirectory): string
    {
        if (trim($outputDirectory) !== '') {
            return $this->resolvePath($outputDirectory);
        }

        return storage_path(sprintf(
            'app/face-search-datasets/caltech-webfaces/%s-caltech-webfaces',
            now()->format('Ymd-His'),
        ));
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
