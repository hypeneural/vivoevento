<?php

namespace App\Modules\FaceSearch\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class XqlfwLocalLoaderService
{
    /**
     * @return array<string, mixed>
     */
    public function run(
        string $variant = 'original',
        string $root = '',
        string $scoresPath = '',
        string $pairsPath = '',
        string $selection = 'official_pairs',
        string $imageSelection = 'score_spread',
        int $offset = 0,
        int $people = 12,
        int $imagesPerPerson = 4,
        int $minImagesPerPerson = 2,
        string $outputDirectory = '',
        string $pythonBinary = 'python',
    ): array {
        $normalizedVariant = $this->normalizeVariant($variant);
        $normalizedSelection = $this->normalizeSelection($selection);
        $normalizedImageSelection = $this->normalizeImageSelection($imageSelection);
        $resolvedRoot = $this->resolveRoot($normalizedVariant, $root);
        $resolvedScoresPath = $this->resolveScoresPath($scoresPath);
        $resolvedPairsPath = $this->resolvePairsPath($pairsPath);
        $resolvedOutputDirectory = $this->resolveOutputDirectory($normalizedVariant, $outputDirectory);
        $scriptPath = base_path('app/Modules/FaceSearch/Support/export_xqlfw.py');

        if (! is_dir($resolvedRoot)) {
            throw new RuntimeException(sprintf('XQLFW root [%s] does not exist.', $resolvedRoot));
        }

        if (! File::exists($resolvedScoresPath)) {
            throw new RuntimeException(sprintf('XQLFW scores file [%s] does not exist.', $resolvedScoresPath));
        }

        if ($normalizedSelection === 'official_pairs' && ! File::exists($resolvedPairsPath)) {
            throw new RuntimeException(sprintf('XQLFW pairs file [%s] does not exist.', $resolvedPairsPath));
        }

        if (! File::exists($scriptPath)) {
            throw new RuntimeException(sprintf('XQLFW exporter script [%s] does not exist.', $scriptPath));
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
                '--scores-path',
                $resolvedScoresPath,
                '--pairs-path',
                File::exists($resolvedPairsPath) ? $resolvedPairsPath : '',
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
                'XQLFW loader failed with exit code [%s]. %s',
                (string) ($result->exitCode() ?? 'unknown'),
                trim($result->output() . PHP_EOL . $result->errorOutput()),
            ));
        }

        try {
            $payload = json_decode((string) $result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('XQLFW loader returned invalid JSON.', previous: $exception);
        }

        if (! is_array($payload) || ! is_string($payload['manifest_path'] ?? null)) {
            throw new RuntimeException('XQLFW loader did not return a valid manifest payload.');
        }

        if (! File::exists((string) $payload['manifest_path'])) {
            throw new RuntimeException(sprintf(
                'XQLFW loader reported manifest [%s], but the file does not exist.',
                (string) $payload['manifest_path'],
            ));
        }

        $payload['variant'] = $normalizedVariant;
        $payload['selection'] = $normalizedSelection;
        $payload['image_selection'] = $normalizedImageSelection;
        $payload['source_root'] = $resolvedRoot;
        $payload['scores_path'] = $resolvedScoresPath;
        $payload['pairs_path'] = File::exists($resolvedPairsPath) ? $resolvedPairsPath : null;
        $payload['output_dir'] = $resolvedOutputDirectory;

        return $payload;
    }

    private function normalizeVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));

        return in_array($variant, ['original', 'aligned_112'], true) ? $variant : 'original';
    }

    private function normalizeSelection(string $selection): string
    {
        $selection = strtolower(trim($selection));

        return in_array($selection, ['official_pairs', 'highest_quality', 'sequential'], true)
            ? $selection
            : 'official_pairs';
    }

    private function normalizeImageSelection(string $imageSelection): string
    {
        $imageSelection = strtolower(trim($imageSelection));

        return in_array($imageSelection, ['score_spread', 'top_score', 'sequential'], true)
            ? $imageSelection
            : 'score_spread';
    }

    private function resolveRoot(string $variant, string $root): string
    {
        if (trim($root) !== '') {
            return $this->resolvePath($root);
        }

        $default = $variant === 'aligned_112'
            ? '%USERPROFILE%/Desktop/model/extracted/xqlfw_aligned_112/xqlfw_aligned_112'
            : '%USERPROFILE%/Desktop/model/extracted/xqlfw/lfw_original_imgs_min_qual0.85variant11';

        return $this->resolvePath($default);
    }

    private function resolveScoresPath(string $scoresPath): string
    {
        if (trim($scoresPath) !== '') {
            return $this->resolvePath($scoresPath);
        }

        return $this->resolvePath('%USERPROFILE%/Desktop/model/xqlfw_scores.txt');
    }

    private function resolvePairsPath(string $pairsPath): string
    {
        if (trim($pairsPath) !== '') {
            return $this->resolvePath($pairsPath);
        }

        return $this->resolvePath('%USERPROFILE%/Desktop/model/xqlfw_pairs.txt');
    }

    private function resolveOutputDirectory(string $variant, string $outputDirectory): string
    {
        if (trim($outputDirectory) !== '') {
            return $this->resolvePath($outputDirectory);
        }

        return storage_path(sprintf(
            'app/face-search-datasets/xqlfw/%s-xqlfw-%s',
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
