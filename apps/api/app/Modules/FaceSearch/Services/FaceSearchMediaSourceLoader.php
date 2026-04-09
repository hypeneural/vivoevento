<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FaceSearchMediaSourceLoader
{
    /**
     * @return array{disk:string,path:string,binary:string,source_ref:string}
     */
    public function loadImageBinary(EventMedia $media): array
    {
        $media->loadMissing('variants');

        foreach (['gallery', 'wall', 'fast_preview'] as $variantKey) {
            $variant = $media->variants->firstWhere('variant_key', $variantKey);

            if ($variant?->path && Storage::disk($variant->disk ?: 'public')->exists($variant->path)) {
                $disk = $variant->disk ?: 'public';
                $path = $variant->path;

                return [
                    'disk' => $disk,
                    'path' => $path,
                    'binary' => Storage::disk($disk)->get($path),
                    'source_ref' => "{$disk}:{$path}",
                ];
            }
        }

        $path = $media->originalStoragePath();
        $disk = $media->originalStorageDisk();

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Arquivo fonte nao encontrado para indexacao facial.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'binary' => Storage::disk($disk)->get($path),
            'source_ref' => "{$disk}:{$path}",
        ];
    }
}
