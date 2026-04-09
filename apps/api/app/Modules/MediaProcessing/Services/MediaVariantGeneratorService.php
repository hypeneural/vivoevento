<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;
use Throwable;

class MediaVariantGeneratorService
{
    public function __construct(
        private readonly PerceptualHashService $perceptualHashService,
        private readonly VideoMetadataExtractorService $videoMetadataExtractor,
    ) {}

    /**
     * @return array{
     *   generated_count:int,
     *   variant_keys:array<int, string>,
     *   source_width:int|null,
     *   source_height:int|null,
     *   perceptual_hash:string|null
     * }
     */
    public function generate(EventMedia $media): array
    {
        if ($media->media_type === 'video') {
            return $this->generateVideoVariants($media);
        }

        if ($media->media_type !== 'image') {
            return [
                'generated_count' => 0,
                'variant_keys' => [],
                'source_width' => $media->width,
                'source_height' => $media->height,
                'perceptual_hash' => null,
            ];
        }

        $disk = $media->originalStorageDisk();
        $path = $media->originalStoragePath();

        if (! $path) {
            throw new RuntimeException('Midia sem caminho de origem para gerar variantes.');
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException("Arquivo original nao encontrado em {$disk}:{$path}");
        }

        $binary = Storage::disk($disk)->get($path);
        $source = Image::decode($binary);
        $perceptualHash = $this->perceptualHashService->generateFromBinary($binary);

        $media->forceFill([
            'width' => $media->width ?: $source->width(),
            'height' => $media->height ?: $source->height(),
        ])->save();

        $generatedKeys = [];

        foreach ($this->definitions() as $definition) {
            $image = Image::decode($binary);

            if ($definition['mode'] === 'cover') {
                $image = $image->coverDown($definition['width'], $definition['height']);
            } else {
                $image = $image->scaleDown(
                    width: $definition['width'],
                    height: $definition['height'],
                );
            }

            $encoded = $image->encodeUsingMediaType('image/webp', $definition['quality']);
            $variantPath = "events/{$media->event_id}/variants/{$media->id}/{$definition['key']}.webp";

            Storage::disk('public')->put($variantPath, (string) $encoded);

            EventMediaVariant::query()->updateOrCreate(
                [
                    'event_media_id' => $media->id,
                    'variant_key' => $definition['key'],
                ],
                [
                    'disk' => 'public',
                    'path' => $variantPath,
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'size_bytes' => strlen((string) $encoded),
                    'mime_type' => 'image/webp',
                ],
            );

            $generatedKeys[] = $definition['key'];
        }

        return [
            'generated_count' => count($generatedKeys),
            'variant_keys' => $generatedKeys,
            'source_width' => $source->width(),
            'source_height' => $source->height(),
            'perceptual_hash' => $perceptualHash,
        ];
    }

    /**
     * @return array{
     *   generated_count:int,
     *   variant_keys:array<int, string>,
     *   source_width:int|null,
     *   source_height:int|null,
     *   perceptual_hash:string|null
     * }
     */
    private function generateVideoVariants(EventMedia $media): array
    {
        $disk = $media->originalStorageDisk();
        $path = $media->originalStoragePath();

        if (! $path) {
            throw new RuntimeException('Midia sem caminho de origem para gerar variantes.');
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException("Arquivo original nao encontrado em {$disk}:{$path}");
        }

        $syncedMetadata = $this->videoMetadataExtractor->extractFromStoredAsset(
            disk: $disk,
            path: $path,
            mimeType: $media->mime_type,
            hints: [
                'width' => $media->width,
                'height' => $media->height,
                'duration_seconds' => $media->duration_seconds,
                'has_audio' => $media->has_audio,
                'video_codec' => $media->video_codec,
                'audio_codec' => $media->audio_codec,
                'bitrate' => $media->bitrate,
                'container' => $media->container,
            ],
        );

        $media->forceFill($this->nonNullMetadata($syncedMetadata))->save();

        $sourceWidth = $syncedMetadata['width'] ?? $media->width;
        $sourceHeight = $syncedMetadata['height'] ?? $media->height;
        $inputAbsolutePath = Storage::disk($disk)->path($path);
        $variantDirectory = "events/{$media->event_id}/variants/{$media->id}";
        Storage::disk('public')->makeDirectory($variantDirectory);

        $generatedKeys = [];

        foreach ($this->videoDefinitions($sourceWidth, $sourceHeight) as $definition) {
            $variantPath = "{$variantDirectory}/{$definition['key']}.mp4";
            $variantAbsolutePath = Storage::disk('public')->path($variantPath);

            $this->transcodeVideoVariant(
                inputAbsolutePath: $inputAbsolutePath,
                outputAbsolutePath: $variantAbsolutePath,
                maxDimension: $definition['max_dimension'],
            );

            $variantMetadata = $this->videoMetadataExtractor->extractFromStoredAsset(
                disk: 'public',
                path: $variantPath,
                mimeType: 'video/mp4',
            );

            EventMediaVariant::query()->updateOrCreate(
                [
                    'event_media_id' => $media->id,
                    'variant_key' => $definition['key'],
                ],
                [
                    'disk' => 'public',
                    'path' => $variantPath,
                    'width' => $variantMetadata['width'],
                    'height' => $variantMetadata['height'],
                    'size_bytes' => $this->resolveFileSize('public', $variantPath),
                    'mime_type' => 'video/mp4',
                ],
            );

            $generatedKeys[] = $definition['key'];
        }

        $posterPath = "{$variantDirectory}/wall_video_poster.jpg";
        $posterAbsolutePath = Storage::disk('public')->path($posterPath);
        $this->generateVideoPoster($inputAbsolutePath, $posterAbsolutePath);
        $posterDimensions = @getimagesize($posterAbsolutePath) ?: [null, null];
        $posterWidth = $posterDimensions[0] ?: $sourceWidth;
        $posterHeight = $posterDimensions[1] ?: $sourceHeight;

        EventMediaVariant::query()->updateOrCreate(
            [
                'event_media_id' => $media->id,
                'variant_key' => 'wall_video_poster',
            ],
            [
                'disk' => 'public',
                'path' => $posterPath,
                'width' => $posterWidth,
                'height' => $posterHeight,
                'size_bytes' => $this->resolveFileSize('public', $posterPath),
                'mime_type' => 'image/jpeg',
            ],
        );

        $generatedKeys[] = 'wall_video_poster';

        return [
            'generated_count' => count($generatedKeys),
            'variant_keys' => $generatedKeys,
            'source_width' => $sourceWidth,
            'source_height' => $sourceHeight,
            'perceptual_hash' => null,
        ];
    }

    /**
     * @return array<int, array{key:string,width:int,height:int,mode:string,quality:int}>
     */
    private function definitions(): array
    {
        return [
            [
                'key' => 'fast_preview',
                'width' => 512,
                'height' => 512,
                'mode' => 'scale',
                'quality' => 78,
            ],
            [
                'key' => 'thumb',
                'width' => 480,
                'height' => 480,
                'mode' => 'cover',
                'quality' => 80,
            ],
            [
                'key' => 'moderation_thumb',
                'width' => 640,
                'height' => 640,
                'mode' => 'cover',
                'quality' => 80,
            ],
            [
                'key' => 'moderation_preview',
                'width' => 1280,
                'height' => 1280,
                'mode' => 'scale',
                'quality' => 82,
            ],
            [
                'key' => 'gallery',
                'width' => 1600,
                'height' => 1600,
                'mode' => 'scale',
                'quality' => 82,
            ],
            [
                'key' => 'wall',
                'width' => 1920,
                'height' => 1920,
                'mode' => 'scale',
                'quality' => 84,
            ],
        ];
    }

    /**
     * @return array<int, array{key:string,max_dimension:int}>
     */
    private function videoDefinitions(?int $sourceWidth, ?int $sourceHeight): array
    {
        $maxSourceDimension = max((int) ($sourceWidth ?? 0), (int) ($sourceHeight ?? 0));

        $definitions = [
            [
                'key' => 'wall_video_720p',
                'max_dimension' => 1280,
            ],
        ];

        if ($maxSourceDimension >= 1920) {
            $definitions[] = [
                'key' => 'wall_video_1080p',
                'max_dimension' => 1920,
            ];
        }

        return $definitions;
    }

    private function transcodeVideoVariant(
        string $inputAbsolutePath,
        string $outputAbsolutePath,
        int $maxDimension,
    ): void {
        $result = Process::timeout(300)->run([
            $this->ffmpegBinary(),
            '-y',
            '-i',
            $inputAbsolutePath,
            '-map',
            '0:v:0',
            '-map',
            '0:a?',
            '-vf',
            $this->scaleFilter($maxDimension),
            '-c:v',
            'libx264',
            '-preset',
            'veryfast',
            '-profile:v',
            'high',
            '-pix_fmt',
            'yuv420p',
            '-movflags',
            '+faststart',
            '-crf',
            '23',
            '-c:a',
            'aac',
            '-b:a',
            '128k',
            '-ac',
            '2',
            $outputAbsolutePath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Falha ao gerar variante de video para wall: '.trim($result->errorOutput() ?: $result->output()));
        }
    }

    private function generateVideoPoster(string $inputAbsolutePath, string $outputAbsolutePath): void
    {
        $result = Process::timeout(120)->run([
            $this->ffmpegBinary(),
            '-y',
            '-i',
            $inputAbsolutePath,
            '-vf',
            'thumbnail,'.$this->scaleFilter(1280),
            '-frames:v',
            '1',
            $outputAbsolutePath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Falha ao gerar poster de video para wall: '.trim($result->errorOutput() ?: $result->output()));
        }
    }

    private function scaleFilter(int $maxDimension): string
    {
        return "scale='if(gte(iw,ih),min({$maxDimension},iw),-2)':'if(gte(iw,ih),-2,min({$maxDimension},ih))'";
    }

    private function ffmpegBinary(): string
    {
        return (string) config('media_processing.ffmpeg_binary', 'ffmpeg');
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
     * @return array<string, mixed>
     */
    private function nonNullMetadata(array $metadata): array
    {
        return collect($metadata)
            ->filter(fn ($value) => $value !== null)
            ->all();
    }

    private function resolveFileSize(string $disk, string $path): ?int
    {
        try {
            return Storage::disk($disk)->size($path);
        } catch (Throwable) {
            try {
                $absolutePath = Storage::disk($disk)->path($path);

                return is_file($absolutePath) ? filesize($absolutePath) ?: null : null;
            } catch (Throwable) {
                return null;
            }
        }
    }
}
