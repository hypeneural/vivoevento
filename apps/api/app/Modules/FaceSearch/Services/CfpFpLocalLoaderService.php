<?php

namespace App\Modules\FaceSearch\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class CfpFpLocalLoaderService
{
    /**
     * @return array<string, mixed>
     */
    public function run(
        string $root = '',
        string $imageSelection = 'spread',
        int $offset = 0,
        int $people = 12,
        int $frontalPerPerson = 2,
        int $profilePerPerson = 2,
        string $outputDirectory = '',
        string $pythonBinary = 'python',
    ): array {
        $normalizedImageSelection = $this->normalizeImageSelection($imageSelection);
        $resolvedRoot = $this->resolveRoot($root);
        $resolvedOutputDirectory = $this->resolveOutputDirectory($outputDirectory);
        $scriptPath = base_path('app/Modules/FaceSearch/Support/export_cfp_fp.py');

        if (! is_dir($resolvedRoot)) {
            throw new RuntimeException(sprintf('CFP-FP root [%s] does not exist.', $resolvedRoot));
        }

        if (! File::exists($scriptPath)) {
            throw new RuntimeException(sprintf('CFP-FP exporter script [%s] does not exist.', $scriptPath));
        }

        File::ensureDirectoryExists($resolvedOutputDirectory);

        $result = Process::path(base_path())
            ->timeout(1800)
            ->run([
                $pythonBinary !== '' ? $pythonBinary : 'python',
                $scriptPath,
                '--root',
                $resolvedRoot,
                '--image-selection',
                $normalizedImageSelection,
                '--offset',
                (string) max(0, $offset),
                '--people',
                (string) max(2, $people),
                '--frontal-per-person',
                (string) max(1, $frontalPerPerson),
                '--profile-per-person',
                (string) max(1, $profilePerPerson),
                '--output-dir',
                $resolvedOutputDirectory,
            ]);

        if (! $result->successful()) {
            throw new RuntimeException(sprintf(
                'CFP-FP loader failed with exit code [%s]. %s',
                (string) ($result->exitCode() ?? 'unknown'),
                trim($result->output() . PHP_EOL . $result->errorOutput()),
            ));
        }

        try {
            $payload = json_decode((string) $result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('CFP-FP loader returned invalid JSON.', previous: $exception);
        }

        if (! is_array($payload) || ! is_string($payload['manifest_path'] ?? null)) {
            throw new RuntimeException('CFP-FP loader did not return a valid manifest payload.');
        }

        if (! File::exists((string) $payload['manifest_path'])) {
            throw new RuntimeException(sprintf(
                'CFP-FP loader reported manifest [%s], but the file does not exist.',
                (string) $payload['manifest_path'],
            ));
        }

        $payload['image_selection'] = $normalizedImageSelection;
        $payload['source_root'] = $resolvedRoot;
        $payload['output_dir'] = $resolvedOutputDirectory;

        return $payload;
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

        return $this->resolvePath('%USERPROFILE%/Desktop/model/extracted/cfp_fp/cfp-dataset/Data/Images');
    }

    private function resolveOutputDirectory(string $outputDirectory): string
    {
        if (trim($outputDirectory) !== '') {
            return $this->resolvePath($outputDirectory);
        }

        return storage_path(sprintf(
            'app/face-search-datasets/cfp-fp/%s-cfp-fp',
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
