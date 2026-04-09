<?php

namespace App\Modules\MediaProcessing\Services;

use Symfony\Component\Process\ExecutableFinder;

class MediaToolingStatusService
{
    public function payload(): array
    {
        $ffmpegBin = (string) config('media_processing.ffmpeg_binary', 'ffmpeg');
        $ffprobeBin = (string) config('media_processing.ffprobe_binary', 'ffprobe');
        $ffmpegResolved = $this->resolveBinary($ffmpegBin);
        $ffprobeResolved = $this->resolveBinary($ffprobeBin);

        return [
            'ffmpeg_bin' => $ffmpegBin,
            'ffprobe_bin' => $ffprobeBin,
            'ffmpeg_available' => $ffmpegResolved !== null,
            'ffprobe_available' => $ffprobeResolved !== null,
            'ffmpeg_resolved_path' => $ffmpegResolved,
            'ffprobe_resolved_path' => $ffprobeResolved,
            'ready' => $ffmpegResolved !== null && $ffprobeResolved !== null,
        ];
    }

    private function resolveBinary(string $binary): ?string
    {
        $normalized = trim($binary);

        if ($normalized === '') {
            return null;
        }

        if ($this->looksLikePath($normalized)) {
            return is_file($normalized) ? $normalized : null;
        }

        return (new ExecutableFinder())->find($normalized) ?: null;
    }

    private function looksLikePath(string $binary): bool
    {
        return str_contains($binary, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $binary) === 1
            || str_starts_with($binary, './')
            || str_starts_with($binary, '../');
    }
}
