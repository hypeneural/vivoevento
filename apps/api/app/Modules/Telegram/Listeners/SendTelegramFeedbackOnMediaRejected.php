<?php

namespace App\Modules\Telegram\Listeners;

use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\Telegram\Services\TelegramFeedbackAutomationService;
use App\Modules\Telegram\Services\TelegramFeedbackContextResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTelegramFeedbackOnMediaRejected implements ShouldQueue
{
    public string $queue = 'telegram-send';

    public function handle(MediaRejected $event): void
    {
        $media = $event->resolveMedia();

        if (! $media || ! $media->event || ! $media->inboundMessage) {
            return;
        }

        $context = app(TelegramFeedbackContextResolver::class)->fromInboundMessage($media->inboundMessage);

        if (! $context) {
            return;
        }

        app(TelegramFeedbackAutomationService::class)->sendRejectedFeedback(
            event: $media->event,
            context: $context,
            inboundMessage: $media->inboundMessage,
            eventMedia: $media,
        );
    }
}
