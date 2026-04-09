<?php

namespace App\Modules\Wall\Support;

use App\Modules\MediaProcessing\Models\EventMedia;

class WallVideoPolicyLabelResolver
{
    public static function fromMedia(EventMedia $media): ?string
    {
        return self::resolve(
            ($media->media_type ?? 'image') === 'video',
            $media->duration_seconds,
            [
                'reasons' => [],
            ],
        );
    }

    /**
     * @param  array{state?:string|null,reasons?:array<int,string>}|null  $videoAdmission
     */
    public static function fromPayload(array $payload, ?array $videoAdmission = null): ?string
    {
        return self::resolve(
            ($payload['type'] ?? 'image') === 'video',
            $payload['duration_seconds'] ?? null,
            $videoAdmission ?? (is_array($payload['video_admission'] ?? null) ? $payload['video_admission'] : null),
        );
    }

    /**
     * @param  array{state?:string|null,reasons?:array<int,string>}|null  $videoAdmission
     */
    private static function resolve(bool $isVideo, ?int $durationSeconds, ?array $videoAdmission): ?string
    {
        if (! $isVideo) {
            return null;
        }

        $reasons = $videoAdmission['reasons'] ?? [];

        if (in_array('duration_over_limit', $reasons, true) || ($durationSeconds !== null && $durationSeconds > 30)) {
            return 'Video longo com politica especial';
        }

        if ($durationSeconds !== null && $durationSeconds <= 15) {
            return 'Video curto';
        }

        if (($videoAdmission['state'] ?? null) === 'eligible_with_fallback') {
            return 'Video com fallback operacional';
        }

        return 'Video com duracao diferenciada';
    }
}
