<?php

namespace App\Modules\FaceSearch\Services;

use Intervention\Image\Laravel\Facades\Image;

class AwsImagePreprocessor
{
    /**
     * @param array<string, mixed> $overrides
     * @return array{
     *   binary:string,
     *   mime_type:string,
     *   width:int,
     *   height:int,
     *   size_bytes:int,
     *   used_derivative:bool
     * }
     */
    public function prepare(string $binary, array $overrides = []): array
    {
        $config = array_replace([
            'max_dimension' => 1920,
            'max_bytes' => 5_242_880,
            'qualities' => [85, 75, 65, 55],
        ], $overrides);

        $image = Image::decode($binary);
        $candidate = $image->scaleDown(
            width: (int) $config['max_dimension'],
            height: (int) $config['max_dimension'],
        );
        $qualities = array_values((array) $config['qualities']);
        $lastBinary = (string) $candidate->encodeUsingMediaType('image/jpeg', 85);

        foreach ($qualities as $quality) {
            $encoded = (string) $candidate->encodeUsingMediaType('image/jpeg', (int) $quality);
            $lastBinary = $encoded;

            if (strlen($encoded) <= (int) $config['max_bytes']) {
                break;
            }
        }

        $encodedImage = Image::decode($lastBinary);

        return [
            'binary' => $lastBinary,
            'mime_type' => 'image/jpeg',
            'width' => $encodedImage->width(),
            'height' => $encodedImage->height(),
            'size_bytes' => strlen($lastBinary),
            'used_derivative' => true,
        ];
    }
}
