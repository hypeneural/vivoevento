<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Shared\Support\AssetUrlService;

class MediaAssetUrlService
{
    public function __construct(
        private readonly AssetUrlService $assets,
    ) {}

    public function toPublicUrl(?string $path, string $disk = 'public'): ?string
    {
        return $this->assets->toPublicUrl($path, $disk);
    }

    public function resolve(EventMedia $media): ?string
    {
        return $this->thumbnail($media);
    }

    public function wall(EventMedia $media, ?string $preferredVideoVariant = null): ?string
    {
        return $this->wallAsset($media, $preferredVideoVariant)['url'];
    }

    public function thumbnail(EventMedia $media): ?string
    {
        return $this->thumbnailAsset($media)['url'];
    }

    /**
     * @return array{url: ?string, source: ?string}
     */
    public function thumbnailAsset(EventMedia $media): array
    {
        if ($media->media_type === 'video') {
            $variant = $this->posterVariant($media);

            return $variant
                ? $this->variantAsset($variant)
                : $this->originalAsset($media);
        }

        $variant = $this->firstVariant($media, ['thumb', 'gallery', 'wall']);

        return $variant
            ? $this->variantAsset($variant)
            : $this->originalAsset($media);
    }

    public function preview(EventMedia $media): ?string
    {
        return $this->previewAsset($media)['url'];
    }

    /**
     * @return array{url: ?string, source: ?string}
     */
    public function moderationThumbnailAsset(EventMedia $media): array
    {
        if ($media->media_type === 'video') {
            return $this->posterAsset($media);
        }

        $variant = $this->firstVariant($media, ['moderation_thumb']);

        return $variant
            ? $this->variantAsset($variant)
            : ['url' => null, 'source' => null];
    }

    /**
     * @return array{url: ?string, source: ?string}
     */
    public function moderationPreviewAsset(EventMedia $media): array
    {
        if ($media->media_type === 'video') {
            $variant = $this->wallVariant($media);

            return $variant
                ? $this->variantAsset($variant)
                : ['url' => null, 'source' => null];
        }

        $variant = $this->firstVariant($media, ['moderation_preview']);

        return $variant
            ? $this->variantAsset($variant)
            : ['url' => null, 'source' => null];
    }

    /**
     * @return array{url: ?string, source: ?string}
     */
    public function previewAsset(EventMedia $media): array
    {
        if ($media->media_type === 'video') {
            return $this->wallAsset($media);
        }

        $variant = $this->firstVariant($media, ['fast_preview', 'gallery', 'wall', 'thumb']);

        return $variant
            ? $this->variantAsset($variant)
            : $this->originalAsset($media);
    }

    public function wallVariantKey(EventMedia $media, ?string $preferredVideoVariant = null): ?string
    {
        return $this->wallVariant($media, $preferredVideoVariant)?->variant_key;
    }

    public function poster(EventMedia $media): ?string
    {
        return $this->posterAsset($media)['url'];
    }

    public function posterVariantKey(EventMedia $media): ?string
    {
        return $this->posterVariant($media)?->variant_key;
    }

    public function original(EventMedia $media): ?string
    {
        return $this->originalAsset($media)['url'];
    }

    /**
     * @return array{url: ?string, source: ?string}
     */
    private function wallAsset(EventMedia $media, ?string $preferredVideoVariant = null): array
    {
        $variant = $this->wallVariant($media, $preferredVideoVariant);

        return $variant
            ? $this->variantAsset($variant)
            : $this->originalAsset($media);
    }

    /**
     * @return array{url: ?string, source: ?string}
     */
    private function posterAsset(EventMedia $media): array
    {
        $variant = $this->posterVariant($media);

        return $variant
            ? $this->variantAsset($variant)
            : ['url' => null, 'source' => null];
    }

    /**
     * @return array{url: ?string, source: ?string}
     */
    private function originalAsset(EventMedia $media): array
    {
        $path = $media->originalStoragePath();

        if (! $path) {
            return ['url' => null, 'source' => null];
        }

        return [
            'url' => $this->assets->toPublicUrl($path, $media->originalStorageDisk()),
            'source' => 'original',
        ];
    }

    /**
     * @return array{url: ?string, source: ?string}
     */
    private function variantAsset(EventMediaVariant $variant): array
    {
        return [
            'url' => $this->assets->toPublicUrl($variant->path, $variant->disk ?: 'public'),
            'source' => $variant->variant_key,
        ];
    }

    private function wallVariant(EventMedia $media, ?string $preferredVideoVariant = null): ?EventMediaVariant
    {
        $videoOrder = ['wall_video_720p', 'wall_video_1080p', 'wall', 'gallery'];

        if ($media->media_type === 'video' && $preferredVideoVariant && $preferredVideoVariant !== 'original') {
            $videoOrder = array_values(array_unique([
                $preferredVideoVariant,
                ...$videoOrder,
            ]));
        }

        return $this->firstVariant(
            $media,
            $media->media_type === 'video'
                ? $videoOrder
                : ['wall', 'gallery'],
        );
    }

    private function posterVariant(EventMedia $media): ?EventMediaVariant
    {
        return $this->firstVariant($media, ['wall_video_poster', 'poster', 'thumb']);
    }

    /**
     * @param  array<int, string>  $variantKeys
     */
    private function firstVariant(EventMedia $media, array $variantKeys): ?EventMediaVariant
    {
        if (! $media->relationLoaded('variants')) {
            $media->load('variants');
        }

        foreach ($variantKeys as $variantKey) {
            $variant = $media->variants->firstWhere('variant_key', $variantKey);

            if ($variant?->path) {
                return $variant;
            }
        }

        return null;
    }
}
