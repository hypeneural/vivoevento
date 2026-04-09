<?php

namespace App\Modules\Wall\Support;

class WallLayoutHintResolver
{
    public function resolve(string $requestedLayout, array $media): string
    {
        if ($requestedLayout !== 'auto') {
            return $requestedLayout;
        }

        $orientation = $media['orientation'] ?? null;
        $isFeatured = (bool) ($media['is_featured'] ?? false);
        $hasCaption = filled(trim((string) ($media['caption'] ?? '')));

        return match ($orientation) {
            'vertical' => $isFeatured ? 'spotlight' : ($hasCaption ? 'split' : 'cinematic'),
            'squareish' => $isFeatured ? 'polaroid' : ($hasCaption ? 'gallery' : 'fullscreen'),
            default => $isFeatured && ! $hasCaption ? 'kenburns' : ($hasCaption ? 'cinematic' : 'fullscreen'),
        };
    }
}
