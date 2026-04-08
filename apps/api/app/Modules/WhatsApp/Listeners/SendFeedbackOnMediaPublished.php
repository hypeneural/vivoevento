<?php

namespace App\Modules\WhatsApp\Listeners;

use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaIntelligence\Services\PublishedMediaReplyTextResolver;
use App\Modules\WhatsApp\Services\WhatsAppFeedbackAutomationService;
use App\Modules\WhatsApp\Services\WhatsAppFeedbackContextResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFeedbackOnMediaPublished implements ShouldQueue
{
    public string $queue = 'whatsapp-send';

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

        $context = app(WhatsAppFeedbackContextResolver::class)->fromInboundMessage($media->inboundMessage);

        if (! $context) {
            return;
        }

        $instance = $media->event->defaultWhatsAppInstance;

        if (! $instance) {
            return;
        }

        $replyResolution = app(PublishedMediaReplyTextResolver::class)->resolveContext($media);

        app(WhatsAppFeedbackAutomationService::class)->sendPublishedFeedback(
            event: $media->event,
            instance: $instance,
            context: $context,
            inboundMessage: $media->inboundMessage,
            eventMedia: $media,
            replyText: $replyResolution['reply_text'] ?? null,
            resolution: $replyResolution,
        );
    }
}
