<?php

namespace App\Modules\MediaProcessing\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class VideoMetadataExtractorService
{
    /**
     * @param  array<string, mixed>  $hints
     * @return array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }
     */
    public function extractFromStoredAsset(
        string $disk,
        string $path,
        ?string $mimeType = null,
        array $hints = [],
    ): array {
        $hintMetadata = $this->metadataFromHints($hints, $mimeType, $path);
        $absolutePath = $this->resolveAbsolutePath($disk, $path);

        if ($absolutePath === null || ! is_file($absolutePath) || ! $this->shouldProbe($hintMetadata)) {
            return $hintMetadata;
        }

        $probeMetadata = $this->probeWithFfprobe($absolutePath, $mimeType, $path);

        return $this->mergeMetadata($hintMetadata, $probeMetadata);
    }

    /**
     * @param  array<string, mixed>  $hints
     * @return array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }
     */
    private function metadataFromHints(array $hints, ?string $mimeType, ?string $path): array
    {
        return [
            'width' => $this->toPositiveInt($this->firstValue($hints, [
                'media.width',
                'video.width',
                'width',
            ])),
            'height' => $this->toPositiveInt($this->firstValue($hints, [
                'media.height',
                'video.height',
                'height',
            ])),
            'duration_seconds' => $this->toDurationSeconds($this->firstValue($hints, [
                'media.duration_seconds',
                'media.duration',
                'video.duration_seconds',
                'video.duration',
                'video.seconds',
                'duration_seconds',
                'duration',
                'seconds',
            ])),
            'has_audio' => $this->toNullableBool($this->firstValue($hints, [
                'media.has_audio',
                'video.has_audio',
                'has_audio',
            ])),
            'video_codec' => $this->toNullableString($this->firstValue($hints, [
                'media.video_codec',
                'video.video_codec',
                'video.codec',
                'video_codec',
            ])),
            'audio_codec' => $this->toNullableString($this->firstValue($hints, [
                'media.audio_codec',
                'video.audio_codec',
                'audio.codec',
                'audio_codec',
            ])),
            'bitrate' => $this->toPositiveInt($this->firstValue($hints, [
                'media.bitrate',
                'video.bitrate',
                'bitrate',
            ])),
            'container' => $this->normalizeContainer(
                $this->toNullableString($this->firstValue($hints, [
                    'media.container',
                    'video.container',
                    'container',
                ])),
                $mimeType,
                $path,
            ),
        ];
    }

    /**
     * @return array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }
     */
    private function probeWithFfprobe(string $absolutePath, ?string $mimeType, ?string $path): array
    {
        $result = Process::timeout(15)->run([
            $this->ffprobeBinary(),
            '-v',
            'error',
            '-print_format',
            'json',
            '-show_streams',
            '-show_format',
            $absolutePath,
        ]);

        if (! $result->successful()) {
            return $this->emptyMetadata($mimeType, $path);
        }

        $payload = json_decode($result->output(), true);

        if (! is_array($payload)) {
            return $this->emptyMetadata($mimeType, $path);
        }

        $streams = collect(Arr::get($payload, 'streams', []))
            ->filter(fn ($stream): bool => is_array($stream))
            ->values();

        $videoStream = $streams->first(fn (array $stream): bool => ($stream['codec_type'] ?? null) === 'video');
        $audioStream = $streams->first(fn (array $stream): bool => ($stream['codec_type'] ?? null) === 'audio');
        $format = Arr::get($payload, 'format', []);

        return [
            'width' => $this->toPositiveInt($videoStream['width'] ?? null),
            'height' => $this->toPositiveInt($videoStream['height'] ?? null),
            'duration_seconds' => $this->toDurationSeconds(
                $format['duration'] ?? ($videoStream['duration'] ?? null),
            ),
            'has_audio' => $audioStream !== null,
            'video_codec' => $this->toNullableString($videoStream['codec_name'] ?? null),
            'audio_codec' => $this->toNullableString($audioStream['codec_name'] ?? null),
            'bitrate' => $this->toPositiveInt($format['bit_rate'] ?? ($videoStream['bit_rate'] ?? null)),
            'container' => $this->normalizeContainer(
                $this->toNullableString($format['format_name'] ?? null),
                $mimeType,
                $path,
            ),
        ];
    }

    /**
     * @param  array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }  $preferred
     * @param  array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }  $fallback
     * @return array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }
     */
    private function mergeMetadata(array $preferred, array $fallback): array
    {
        return [
            'width' => $preferred['width'] ?? $fallback['width'],
            'height' => $preferred['height'] ?? $fallback['height'],
            'duration_seconds' => $preferred['duration_seconds'] ?? $fallback['duration_seconds'],
            'has_audio' => $preferred['has_audio'] ?? $fallback['has_audio'],
            'video_codec' => $preferred['video_codec'] ?? $fallback['video_codec'],
            'audio_codec' => $preferred['audio_codec'] ?? $fallback['audio_codec'],
            'bitrate' => $preferred['bitrate'] ?? $fallback['bitrate'],
            'container' => $preferred['container'] ?? $fallback['container'],
        ];
    }

    /**
     * @return array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }
     */
    private function emptyMetadata(?string $mimeType, ?string $path): array
    {
        return [
            'width' => null,
            'height' => null,
            'duration_seconds' => null,
            'has_audio' => null,
            'video_codec' => null,
            'audio_codec' => null,
            'bitrate' => null,
            'container' => $this->normalizeContainer(null, $mimeType, $path),
        ];
    }

    /**
     * @param  array{
     *   width:int|null,
     *   height:int|null,
     *   duration_seconds:int|null,
     *   has_audio:bool|null,
     *   video_codec:string|null,
     *   audio_codec:string|null,
     *   bitrate:int|null,
     *   container:string|null
     * }  $metadata
     */
    private function shouldProbe(array $metadata): bool
    {
        return $metadata['width'] === null
            || $metadata['height'] === null
            || $metadata['duration_seconds'] === null
            || $metadata['has_audio'] === null
            || $metadata['video_codec'] === null
            || $metadata['container'] === null;
    }

    private function resolveAbsolutePath(string $disk, string $path): ?string
    {
        try {
            return Storage::disk($disk)->path($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstValue(array $source, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($source, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function ffprobeBinary(): string
    {
        return (string) config('media_processing.ffprobe_binary', 'ffprobe');
    }

    private function toPositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $normalized = (int) round((float) $value);

        return $normalized > 0 ? $normalized : null;
    }

    private function toDurationSeconds(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $normalized = (int) ceil((float) $value);

        return $normalized > 0 ? $normalized : null;
    }

    private function toNullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? strtolower($normalized) : null;
    }

    private function normalizeContainer(?string $raw, ?string $mimeType, ?string $path): ?string
    {
        $candidates = array_filter([
            $raw,
            $mimeType,
            $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : null,
        ]);

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));

            if ($normalized === '') {
                continue;
            }

            if (str_contains($normalized, 'quicktime') || $normalized === 'mov') {
                return 'mov';
            }

            if (str_contains($normalized, 'mp4')) {
                return 'mp4';
            }

            if (str_contains($normalized, 'm4v')) {
                return 'm4v';
            }

            if (str_contains($normalized, 'webm')) {
                return 'webm';
            }

            if (str_contains($normalized, 'matroska') || str_contains($normalized, 'mkv')) {
                return 'mkv';
            }

            if (str_contains($normalized, 'avi')) {
                return 'avi';
            }
        }

        return null;
    }
}
