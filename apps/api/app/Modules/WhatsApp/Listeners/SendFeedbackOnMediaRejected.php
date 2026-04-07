<?php

namespace App\Modules\WhatsApp\Listeners;

use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\WhatsApp\Services\WhatsAppFeedbackAutomationService;
use App\Modules\WhatsApp\Services\WhatsAppFeedbackContextResolver;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFeedbackOnMediaRejected implements ShouldQueue
{
    public string $queue = 'whatsapp-send';

    public function handle(MediaRejected $event): void
    {
        $media = $event->resolveMedia();

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

        app(WhatsAppFeedbackAutomationService::class)->sendRejectedFeedback(
            event: $media->event,
            instance: $instance,
            context: $context,
            phase: 'rejected',
            inboundMessage: $media->inboundMessage,
            eventMedia: $media,
        );
    }
}
