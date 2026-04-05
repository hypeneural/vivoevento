<?php

namespace App\Modules\MediaProcessing\Services;

class MediaPipelineDegradationPolicy
{
    public function safetyMode(): string
    {
        $mode = (string) config('observability.degradation.media_safety_mode', 'normal');

        return in_array($mode, ['normal', 'review', 'block'], true)
            ? $mode
            : 'normal';
    }

    public function forcedSafetyDecision(): ?string
    {
        return match ($this->safetyMode()) {
            'review' => 'review',
            'block' => 'block',
            default => null,
        };
    }

    public function vlmEnabled(): bool
    {
        return (bool) config('observability.degradation.media_vlm_enabled', true);
    }

    public function faceIndexEnabled(): bool
    {
        return (bool) config('observability.degradation.face_index_enabled', true);
    }
}
