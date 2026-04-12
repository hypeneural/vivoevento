<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

use Illuminate\Support\Facades\Storage;

class GalleryBuilderAssetUrlResolver
{
    /**
     * @param  array<string, mixed>  $pageSchema
     * @return array<string, mixed>
     */
    public function hydratePageSchema(array $pageSchema): array
    {
        $blocks = is_array($pageSchema['blocks'] ?? null) ? $pageSchema['blocks'] : [];

        $pageSchema['blocks'] = $blocks;
        $pageSchema['blocks']['hero'] = $this->withAssetUrl(
            is_array($blocks['hero'] ?? null) ? $blocks['hero'] : [],
        );
        $pageSchema['blocks']['banner_strip'] = $this->withAssetUrl(
            is_array($blocks['banner_strip'] ?? null) ? $blocks['banner_strip'] : [],
        );

        return $pageSchema;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function withAssetUrl(array $block): array
    {
        $block['image_url'] = $this->resolveUrl($block['image_path'] ?? null);

        return $block;
    }

    private function resolveUrl(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
