<?php

namespace App\Modules\FaceSearch\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class CalfwLocalLoaderService
{
    /**
     * @return array<string, mixed>
     */
    public function run(
        string $root = '',
        string $selection = 'largest_identities',
        string $imageSelection = 'spread',
        int $offset = 0,
        int $people = 12,
        int $imagesPerPerson = 4,
        int $minImagesPerPerson = 4,
        string $outputDirectory = '',
        string $pythonBinary = 'python',
    ): array {
        $normalizedSelection = $this->normalizeSelection($selection);
        $normalizedImageSelection = $this->normalizeImageSelection($imageSelection);
        $resolvedRoot = $this->resolveRoot($root);
        $resolvedOutputDirectory = $this->resolveOutputDirectory($outputDirectory);
        $scriptPath = base_path('app/Modules/FaceSearch/Support/export_calfw.py');

        if (! is_dir($resolvedRoot)) {
            throw new RuntimeException(sprintf('CALFW root [%s] does not exist.', $resolvedRoot));
        }

        if (! File::exists($scriptPath)) {
            throw new RuntimeException(sprintf('CALFW exporter script [%s] does not exist.', $scriptPath));
        }

        File::ensureDirectoryExists($resolvedOutputDirectory);

        $result = Process::path(base_path())
            ->timeout(1800)
            ->run([
                $pythonBinary !== '' ? $pythonBinary : 'python',
                $scriptPath,
                '--root',
                $resolvedRoot,
                '--selection',
                $normalizedSelection,
                '--image-selection',
                $normalizedImageSelection,
                '--offset',
                (string) max(0, $offset),
                '--people',
                (string) max(2, $people),
                '--images-per-person',
                (string) max(2, $imagesPerPerson),
                '--min-images-per-person',
                (string) max(2, $minImagesPerPerson),
                '--output-dir',
                $resolvedOutputDirectory,
            ]);

        if (! $result->successful()) {
            throw new RuntimeException(sprintf(
                'CALFW loader failed with exit code [%s]. %s',
                (string) ($result->exitCode() ?? 'unknown'),
                trim($result->output() . PHP_EOL . $result->errorOutput()),
            ));
        }

        try {
            $payload = json_decode((string) $result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('CALFW loader returned invalid JSON.', previous: $exception);
        }

        if (! is_array($payload) || ! is_string($payload['manifest_path'] ?? null)) {
            throw new RuntimeException('CALFW loader did not return a valid manifest payload.');
        }

        if (! File::exists((string) $payload['manifest_path'])) {
            throw new RuntimeException(sprintf(
                'CALFW loader reported manifest [%s], but the file does not exist.',
                (string) $payload['manifest_path'],
            ));
        }

        $payload['selection'] = $normalizedSelection;
        $payload['image_selection'] = $normalizedImageSelection;
        $payload['source_root'] = $resolvedRoot;
        $payload['output_dir'] = $resolvedOutputDirectory;

        return $payload;
    }

    private function normalizeSelection(string $selection): string
    {
        $selection = strtolower(trim($selection));

        return in_array($selection, ['largest_identities', 'sequential'], true)
            ? $selection
            : 'largest_identities';
    }

    private function normalizeImageSelection(string $imageSelection): string
    {
        $imageSelection = strtolower(trim($imageSelection));

        return in_array($imageSelection, ['spread', 'sequential'], true)
            ? $imageSelection
            : 'spread';
    }

    private function resolveRoot(string $root): string
    {
        if (trim($root) !== '') {
            return $this->resolvePath($root);
        }

        return $this->resolvePath('%USERPROFILE%/Desktop/model/extracted/calfw/calfw/aligned images');
    }

    private function resolveOutputDirectory(string $outputDirectory): string
    {
        if (trim($outputDirectory) !== '') {
            return $this->resolvePath($outputDirectory);
        }

        return storage_path(sprintf(
            'app/face-search-datasets/calfw/%s-calfw',
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
