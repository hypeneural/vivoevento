<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
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

    public function thumbnail(EventMedia $media): ?string
    {
        if (! $media->relationLoaded('variants')) {
            $media->load('variants');
        }

        foreach (['thumb', 'gallery', 'wall'] as $variantKey) {
            $variant = $media->variants->firstWhere('variant_key', $variantKey);

            if ($variant?->path) {
                return $this->assets->toPublicUrl($variant->path, $variant->disk ?: 'public');
            }
        }

        return $this->original($media);
    }

    public function preview(EventMedia $media): ?string
    {
        if (! $media->relationLoaded('variants')) {
            $media->load('variants');
        }

        $preferredVariants = $media->media_type === 'video'
            ? ['gallery', 'wall', 'thumb']
            : ['fast_preview', 'gallery', 'wall', 'thumb'];

        foreach ($preferredVariants as $variantKey) {
            $variant = $media->variants->firstWhere('variant_key', $variantKey);

            if ($variant?->path) {
                return $this->assets->toPublicUrl($variant->path, $variant->disk ?: 'public');
            }
        }

        return $this->original($media);
    }

    public function original(EventMedia $media): ?string
    {
        $path = $media->originalStoragePath();

        if (! $path) {
            return null;
        }

        return $this->assets->toPublicUrl($path, $media->originalStorageDisk());
    }
}
