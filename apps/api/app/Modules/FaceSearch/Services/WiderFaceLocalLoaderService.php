<?php

namespace App\Modules\FaceSearch\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class WiderFaceLocalLoaderService
{
    /**
     * @param array<int, string> $splits
     * @return array<string, mixed>
     */
    public function run(
        string $cacheDirectory,
        array $splits = ['validation'],
        string $selection = 'dense_annotations',
        int $limit = 50,
        string $outputDirectory = '',
        string $pythonBinary = 'python',
    ): array {
        $normalizedSplits = $this->normalizeSplits($splits);
        $normalizedSelection = $this->normalizeSelection($selection);
        $resolvedCacheDirectory = $this->resolvePath($cacheDirectory);
        $resolvedOutputDirectory = $this->resolveOutputDirectory($outputDirectory);
        $scriptPath = base_path('app/Modules/FaceSearch/Support/export_wider_face.py');

        if (! File::exists($scriptPath)) {
            throw new RuntimeException(sprintf('WIDER FACE exporter script [%s] does not exist.', $scriptPath));
        }

        File::ensureDirectoryExists($resolvedCacheDirectory);
        File::ensureDirectoryExists($resolvedOutputDirectory);

        $result = Process::path(base_path())
            ->timeout(3600)
            ->run([
                $pythonBinary !== '' ? $pythonBinary : 'python',
                $scriptPath,
                '--cache-dir',
                $resolvedCacheDirectory,
                '--splits',
                implode(',', $normalizedSplits),
                '--selection',
                $normalizedSelection,
                '--limit',
                (string) max(1, $limit),
                '--output-dir',
                $resolvedOutputDirectory,
            ]);

        if (! $result->successful()) {
            throw new RuntimeException(sprintf(
                'WIDER FACE loader failed with exit code [%s]. %s',
                (string) ($result->exitCode() ?? 'unknown'),
                trim($result->output() . PHP_EOL . $result->errorOutput()),
            ));
        }

        try {
            $payload = json_decode((string) $result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('WIDER FACE loader returned invalid JSON.', previous: $exception);
        }

        if (! is_array($payload) || ! is_string($payload['manifest_path'] ?? null)) {
            throw new RuntimeException('WIDER FACE loader did not return a valid manifest payload.');
        }

        if (! File::exists((string) $payload['manifest_path'])) {
            throw new RuntimeException(sprintf(
                'WIDER FACE loader reported manifest [%s], but the file does not exist.',
                (string) $payload['manifest_path'],
            ));
        }

        $payload['cache_dir'] = $resolvedCacheDirectory;
        $payload['selection'] = $normalizedSelection;
        $payload['splits'] = $normalizedSplits;
        $payload['output_dir'] = $resolvedOutputDirectory;

        return $payload;
    }

    /**
     * @param array<int, string> $splits
     * @return array<int, string>
     */
    private function normalizeSplits(array $splits): array
    {
        $normalized = collect($splits)
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
            ->map(fn (string $split): string => $split === 'val' ? 'validation' : $split)
            ->filter(fn (string $split): bool => in_array($split, ['train', 'validation', 'test'], true))
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? ['validation'] : $normalized;
    }

    private function normalizeSelection(string $selection): string
    {
        $selection = strtolower(trim($selection));

        return in_array($selection, ['dense_annotations', 'smallest_face', 'sequential'], true)
            ? $selection
            : 'dense_annotations';
    }

    private function resolveOutputDirectory(string $outputDirectory): string
    {
        if (trim($outputDirectory) !== '') {
            return $this->resolvePath($outputDirectory);
        }

        return storage_path(sprintf(
            'app/face-search-datasets/wider-face/%s-wider-face',
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
