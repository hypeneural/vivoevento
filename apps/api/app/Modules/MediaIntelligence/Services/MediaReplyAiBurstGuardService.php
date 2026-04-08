<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\RateLimiter;

class MediaReplyAiBurstGuardService
{
    /**
     * @return array{
     *   enabled:bool,
     *   allowed:bool,
     *   max_messages:int,
     *   window_minutes:int,
     *   available_in_seconds:int,
     *   attempts:int,
     *   remaining:int,
     *   sender_key:string,
     *   limiter_key:string,
     *   intake_source:string
     * }|null
     */
    public function consume(EventMedia $media): ?array
    {
        $media->loadMissing('event.mediaIntelligenceSettings', 'inboundMessage');

        $settings = $media->event?->mediaIntelligenceSettings;

        if (! $settings?->usesAiAutomaticReply()) {
            return null;
        }

        if (($settings->mode ?? null) === 'gate') {
            return null;
        }

        $global = MediaIntelligenceGlobalSetting::query()->firstOrNew(
            ['id' => 1],
            MediaIntelligenceGlobalSetting::defaultAttributes(),
        );

        if (! (bool) $global->reply_ai_rate_limit_enabled) {
            return null;
        }

        $context = data_get($media->inboundMessage?->normalized_payload_json, '_event_context', []);
        $intakeSource = (string) data_get($context, 'intake_source', $media->source_type);

        if (! in_array($intakeSource, ['whatsapp_group', 'whatsapp_direct'], true)) {
            return null;
        }

        $senderKey = $this->resolveSenderKey($media, $context);

        if ($senderKey === null) {
            return null;
        }

        $maxMessages = max(1, (int) ($global->reply_ai_rate_limit_max_messages ?? 10));
        $windowMinutes = max(1, (int) ($global->reply_ai_rate_limit_window_minutes ?? 10));
        $windowSeconds = $windowMinutes * 60;
        $limiterKey = sprintf('media-ai-reply:%d:%s', $media->event_id, $senderKey);
        $allowed = RateLimiter::attempt(
            $limiterKey,
            $maxMessages,
            static fn (): bool => true,
            $windowSeconds,
        );
        $attempts = min((int) RateLimiter::attempts($limiterKey), $maxMessages);

        return [
            'enabled' => true,
            'allowed' => (bool) $allowed,
            'max_messages' => $maxMessages,
            'window_minutes' => $windowMinutes,
            'available_in_seconds' => $allowed ? 0 : RateLimiter::availableIn($limiterKey),
            'attempts' => $attempts,
            'remaining' => max($maxMessages - $attempts, 0),
            'sender_key' => $senderKey,
            'limiter_key' => $limiterKey,
            'intake_source' => $intakeSource,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveSenderKey(EventMedia $media, array $context): ?string
    {
        $senderKey = trim((string) (
            data_get($context, 'sender_external_id')
            ?: data_get($context, 'sender_phone')
            ?: $media->inboundMessage?->sender_phone
        ));

        return $senderKey !== '' ? $senderKey : null;
    }
}
