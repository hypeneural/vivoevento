<?php

namespace App\Modules\FaceSearch\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class CofwLocalLoaderService
{
    /**
     * @param array<int, string> $splits
     * @return array<string, mixed>
     */
    public function run(
        string $variant = 'color',
        string $root = '',
        array $splits = ['train', 'test'],
        bool $includeLfpw = false,
        int $limit = 0,
        string $outputDirectory = '',
        string $pythonBinary = 'python',
    ): array {
        $normalizedVariant = $this->normalizeVariant($variant);
        $normalizedSplits = $this->normalizeSplits($splits);
        $resolvedRoot = $this->resolveRoot($normalizedVariant, $root);
        $resolvedOutputDirectory = $this->resolveOutputDirectory($normalizedVariant, $outputDirectory);
        $scriptPath = base_path('app/Modules/FaceSearch/Support/export_cofw.py');

        if (! is_dir($resolvedRoot)) {
            throw new RuntimeException(sprintf('COFW root [%s] does not exist.', $resolvedRoot));
        }

        if (! File::exists($scriptPath)) {
            throw new RuntimeException(sprintf('COFW exporter script [%s] does not exist.', $scriptPath));
        }

        File::ensureDirectoryExists($resolvedOutputDirectory);

        $result = Process::path(base_path())
            ->timeout(1800)
            ->run([
                $pythonBinary !== '' ? $pythonBinary : 'python',
                $scriptPath,
                '--variant',
                $normalizedVariant,
                '--root',
                $resolvedRoot,
                '--splits',
                implode(',', $normalizedSplits),
                '--include-lfpw',
                $includeLfpw ? '1' : '0',
                '--limit',
                (string) max(0, $limit),
                '--output-dir',
                $resolvedOutputDirectory,
            ]);

        if (! $result->successful()) {
            throw new RuntimeException(sprintf(
                'COFW loader failed with exit code [%s]. %s',
                (string) ($result->exitCode() ?? 'unknown'),
                trim($result->output() . PHP_EOL . $result->errorOutput()),
            ));
        }

        try {
            $payload = json_decode((string) $result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('COFW loader returned invalid JSON.', previous: $exception);
        }

        if (! is_array($payload) || ! is_string($payload['manifest_path'] ?? null)) {
            throw new RuntimeException('COFW loader did not return a valid manifest payload.');
        }

        if (! File::exists((string) $payload['manifest_path'])) {
            throw new RuntimeException(sprintf(
                'COFW loader reported manifest [%s], but the file does not exist.',
                (string) $payload['manifest_path'],
            ));
        }

        $payload['variant'] = $normalizedVariant;
        $payload['splits'] = $normalizedSplits;
        $payload['include_lfpw'] = $includeLfpw;
        $payload['source_root'] = $resolvedRoot;
        $payload['output_dir'] = $resolvedOutputDirectory;

        return $payload;
    }

    private function normalizeVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));

        return in_array($variant, ['color', 'gray'], true) ? $variant : 'color';
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
            ->flatMap(fn (string $split): array => $split === 'all' ? ['train', 'test'] : [$split])
            ->filter(fn (string $split): bool => in_array($split, ['train', 'test'], true))
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? ['train', 'test'] : $normalized;
    }

    private function resolveRoot(string $variant, string $root): string
    {
        if (trim($root) !== '') {
            return $this->resolvePath($root);
        }

        $default = $variant === 'gray'
            ? '%USERPROFILE%/Desktop/model/extracted/cofw_gray/common/xpburgos/behavior/code/pose'
            : '%USERPROFILE%/Desktop/model/extracted/cofw_color';

        return $this->resolvePath($default);
    }

    private function resolveOutputDirectory(string $variant, string $outputDirectory): string
    {
        if (trim($outputDirectory) !== '') {
            return $this->resolvePath($outputDirectory);
        }

        return storage_path(sprintf(
            'app/face-search-datasets/cofw/%s-cofw-%s',
            now()->format('Ymd-His'),
            $variant,
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
