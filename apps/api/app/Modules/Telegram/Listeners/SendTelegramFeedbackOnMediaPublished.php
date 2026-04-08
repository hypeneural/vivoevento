<?php

namespace App\Modules\Telegram\Listeners;

use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaIntelligence\Services\PublishedMediaReplyTextResolver;
use App\Modules\Telegram\Services\TelegramFeedbackAutomationService;
use App\Modules\Telegram\Services\TelegramFeedbackContextResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTelegramFeedbackOnMediaPublished implements ShouldQueue
{
    public string $queue = 'telegram-send';

    public function handle(MediaPublished $event): void
    {
        $media = $event->resolveMedia()?->loadMissing([
            'event.mediaIntelligenceSettings',
            'latestVlmEvaluation',
            'inboundMessage',
        ]);

        if (! $media || ! $media->event || ! $media->inboundMessage) {
            return;
        }

        $context = app(TelegramFeedbackContextResolver::class)->fromInboundMessage($media->inboundMessage);

        if (! $context) {
            return;
        }

        $replyResolution = app(PublishedMediaReplyTextResolver::class)->resolveContext($media);

        app(TelegramFeedbackAutomationService::class)->sendPublishedFeedback(
            event: $media->event,
            context: $context,
            inboundMessage: $media->inboundMessage,
            eventMedia: $media,
            replyText: $replyResolution['reply_text'] ?? null,
            resolution: $replyResolution,
        );
    }
}
