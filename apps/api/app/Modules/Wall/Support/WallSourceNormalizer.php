<?php

namespace App\Modules\Wall\Support;

class WallSourceNormalizer
{
    public static function normalize(?string $sourceType): string
    {
        return match (true) {
            $sourceType === 'telegram' => 'telegram',
            in_array($sourceType, ['public_upload', 'upload', 'channel'], true) => 'upload',
            in_array($sourceType, ['manual', 'manual_override'], true) => 'manual',
            in_array($sourceType, ['gallery', 'public_link', 'qrcode'], true) => 'gallery',
            default => 'whatsapp',
        };
    }
}
