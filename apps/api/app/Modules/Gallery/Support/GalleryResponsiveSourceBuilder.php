<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Support\AssetUrlService;

class GalleryResponsiveSourceBuilder
{
    public function __construct(
        private readonly AssetUrlService $assets,
    ) {}

    /**
     * @return array{sizes: string, srcset: string, variants: array<int, array<string, mixed>>}
     */
    public function build(EventMedia $media): array
    {
        $media->loadMissing('variants');

        $variants = $media->variants
            ->filter(fn ($variant) => $this->isPublicImageVariant($media->media_type, (string) $variant->variant_key, (string) $variant->mime_type))
            ->filter(fn ($variant) => filled($variant->path) && (int) ($variant->width ?? 0) > 0 && (int) ($variant->height ?? 0) > 0)
            ->sortBy(fn ($variant) => (int) $variant->width)
            ->map(function ($variant): array {
                return [
                    'variant_key' => $variant->variant_key,
                    'src' => $this->assets->toPublicUrl($variant->path, $variant->disk ?: 'public'),
                    'width' => (int) $variant->width,
                    'height' => (int) $variant->height,
                    'mime_type' => $variant->mime_type ?: 'image/jpeg',
                ];
            })
            ->filter(fn (array $variant) => filled($variant['src']))
            ->values()
            ->all();

        if ($variants === [] && $media->media_type === 'image' && $media->originalStoragePath() && $media->width && $media->height) {
            $variants[] = [
                'variant_key' => 'original',
                'src' => $this->assets->toPublicUrl($media->originalStoragePath(), $media->originalStorageDisk()),
                'width' => (int) $media->width,
                'height' => (int) $media->height,
                'mime_type' => $media->mime_type ?: 'image/jpeg',
            ];
        }

        return [
            'sizes' => GalleryBuilderSchemaRegistry::RESPONSIVE_SIZES,
            'srcset' => collect($variants)
                ->map(fn (array $variant) => "{$variant['src']} {$variant['width']}w")
                ->implode(', '),
            'variants' => $variants,
        ];
    }

    private function isPublicImageVariant(string $mediaType, string $variantKey, string $mimeType): bool
    {
        if (! str_starts_with($mimeType, 'image/')) {
            return false;
        }

        if ($mediaType === 'video') {
            return in_array($variantKey, ['wall_video_poster', 'poster', 'thumb'], true);
        }

        return ! str_starts_with($variantKey, 'moderation_');
    }
}
