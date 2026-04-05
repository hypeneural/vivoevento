<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\Storage;

class AssetUrlService
{
    public function toPublicUrl(?string $path, string $disk = 'public'): ?string
    {
        if (! $path || trim($path) === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
        }

        $url = Storage::disk($disk)->url($path);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
