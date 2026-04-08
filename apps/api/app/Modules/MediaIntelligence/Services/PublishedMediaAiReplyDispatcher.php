<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Telegram\Services\TelegramFeedbackAutomationService;
use App\Modules\Telegram\Services\TelegramFeedbackContextResolver;
use App\Modules\WhatsApp\Services\WhatsAppFeedbackAutomationService;
use App\Modules\WhatsApp\Services\WhatsAppFeedbackContextResolver;

class PublishedMediaAiReplyDispatcher
{
    public function __construct(
        private readonly PublishedMediaReplyTextResolver $replyTexts,
        private readonly WhatsAppFeedbackContextResolver $whatsAppContext,
        private readonly WhatsAppFeedbackAutomationService $whatsAppFeedback,
        private readonly TelegramFeedbackContextResolver $telegramContext,
        private readonly TelegramFeedbackAutomationService $telegramFeedback,
    ) {}

    public function dispatchIfEligible(EventMedia $media, ?string $fallbackReplyText = null): void
    {
        $media->loadMissing([
            'event.mediaIntelligenceSettings',
            'event.defaultWhatsAppInstance',
            'inboundMessage',
            'latestVlmEvaluation',
        ]);

        if (! $media->event || ! $media->inboundMessage) {
            return;
        }

        if ($media->publication_status !== PublicationStatus::Published) {
            return;
        }

        $replyResolution = $this->replyTexts->resolveContext($media, $fallbackReplyText);
        $replyText = $replyResolution['reply_text'] ?? null;

        if ($replyText === null) {
            return;
        }

        $context = data_get($media->inboundMessage->normalized_payload_json, '_event_context', []);
        $intakeSource = (string) data_get($context, 'intake_source', $media->source_type);

        if (in_array($intakeSource, ['whatsapp_group', 'whatsapp_direct'], true)) {
            $resolvedContext = $this->whatsAppContext->fromInboundMessage($media->inboundMessage);
            $instance = $media->event->defaultWhatsAppInstance;

            if ($resolvedContext && $instance) {
                $this->whatsAppFeedback->sendPublishedFeedback(
                    event: $media->event,
                    instance: $instance,
                    context: $resolvedContext,
                    inboundMessage: $media->inboundMessage,
                    eventMedia: $media,
                    replyText: $replyText,
                    resolution: $replyResolution,
                );
            }

            return;
        }

        if ($intakeSource === 'telegram') {
            $resolvedContext = $this->telegramContext->fromInboundMessage($media->inboundMessage);

            if ($resolvedContext) {
                $this->telegramFeedback->sendPublishedFeedback(
                    event: $media->event,
                    context: $resolvedContext,
                    inboundMessage: $media->inboundMessage,
                    eventMedia: $media,
                    replyText: $replyText,
                    resolution: $replyResolution,
                );
            }
        }
    }
}
