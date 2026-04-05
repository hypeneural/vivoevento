<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;

class MediaVariantGeneratorService
{
    public function __construct(
        private readonly PerceptualHashService $perceptualHashService,
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
}
