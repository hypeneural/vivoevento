<?php

namespace App\Modules\MediaProcessing\Enums;

enum MediaReprocessStage: string
{
    case Safety = 'safety';
    case Vlm = 'vlm';
    case FaceIndex = 'face_index';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $stage) => $stage->value,
            self::cases(),
        );
    }
}
