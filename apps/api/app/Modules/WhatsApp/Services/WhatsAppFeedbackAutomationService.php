<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\WhatsApp\Clients\DTOs\SendReactionData;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Database\QueryException;

class WhatsAppFeedbackAutomationService
{
    private const DETECTED_REACTION = '⏳';
    private const PUBLISHED_REACTION = '❤️';
    private const REJECTED_REACTION = '🚫';
    private const DEFAULT_REJECT_REPLY = 'Sua midia nao segue as diretrizes do evento. 🛡️';

    public function __construct(
        private readonly WhatsAppMessagingService $messaging,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function sendDetectedReaction(
        Event $event,
        WhatsAppInstance $instance,
        array $context,
        ?InboundMessage $inboundMessage = null,
    ): ?WhatsAppMessageFeedback {
        return $this->sendReaction(
            event: $event,
            instance: $instance,
            context: $context,
            phase: 'detected',
            emoji: self::DETECTED_REACTION,
            inboundMessage: $inboundMessage,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendPublishedReaction(
        Event $event,
        WhatsAppInstance $instance,
        array $context,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): ?WhatsAppMessageFeedback {
        return $this->sendReaction(
            event: $event,
            instance: $instance,
            context: $context,
            phase: 'published',
            emoji: self::PUBLISHED_REACTION,
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendRejectedFeedback(
        Event $event,
        WhatsAppInstance $instance,
        array $context,
        string $phase = 'rejected',
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): void {
        $reaction = $this->sendReaction(
            event: $event,
            instance: $instance,
            context: $context,
            phase: $phase,
            emoji: self::REJECTED_REACTION,
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );

        if (! $this->rejectReplyEnabled($event)) {
            return;
        }

        $this->sendReply(
            event: $event,
            instance: $instance,
            context: $context,
            phase: $phase,
            message: $this->rejectReplyMessage($event),
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendReaction(
        Event $event,
        WhatsAppInstance $instance,
        array $context,
        string $phase,
        string $emoji,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): ?WhatsAppMessageFeedback {
        if (! $this->isFeedbackEligible($event, $context)) {
            return null;
        }

        $feedback = $this->reserveFeedback(
            event: $event,
            instance: $instance,
            context: $context,
            kind: 'reaction',
            phase: $phase,
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );

        if ($feedback === null) {
            return null;
        }

        try {
            $message = $this->messaging->sendReaction(
                $instance,
                new SendReactionData(
                    phone: (string) data_get($context, 'chat_external_id'),
                    reaction: $emoji,
                    messageId: (string) data_get($context, 'provider_message_id'),
                ),
            );

            $feedback->forceFill([
                'status' => 'sent',
                'reaction_emoji' => $emoji,
                'outbound_message_id' => $message->id,
                'attempted_at' => now(),
                'completed_at' => now(),
                'error_message' => null,
            ])->save();

            return $feedback;
        } catch (\Throwable $exception) {
            $feedback->forceFill([
                'status' => 'failed',
                'reaction_emoji' => $emoji,
                'attempted_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendReply(
        Event $event,
        WhatsAppInstance $instance,
        array $context,
        string $phase,
        string $message,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): ?WhatsAppMessageFeedback {
        if (! $this->isFeedbackEligible($event, $context)) {
            return null;
        }

        $feedback = $this->reserveFeedback(
            event: $event,
            instance: $instance,
            context: $context,
            kind: 'reply',
            phase: $phase,
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );

        if ($feedback === null) {
            return null;
        }

        try {
            $reply = $this->messaging->sendText(
                $instance,
                new SendTextData(
                    phone: (string) data_get($context, 'chat_external_id'),
                    message: $message,
                    messageId: (string) data_get($context, 'provider_message_id'),
                    privateAnswer: data_get($context, 'intake_source') === 'whatsapp_group',
                ),
            );

            $feedback->forceFill([
                'status' => 'sent',
                'reply_text' => $message,
                'outbound_message_id' => $reply->id,
                'attempted_at' => now(),
                'completed_at' => now(),
                'error_message' => null,
            ])->save();

            return $feedback;
        } catch (\Throwable $exception) {
            $feedback->forceFill([
                'status' => 'failed',
                'reply_text' => $message,
                'attempted_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function reserveFeedback(
        Event $event,
        WhatsAppInstance $instance,
        array $context,
        string $kind,
        string $phase,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): ?WhatsAppMessageFeedback {
        try {
            return WhatsAppMessageFeedback::query()->create([
                'event_id' => $event->id,
                'instance_id' => $instance->id,
                'inbound_message_id' => $inboundMessage?->id,
                'event_media_id' => $eventMedia?->id,
                'inbound_provider_message_id' => (string) data_get($context, 'provider_message_id'),
                'chat_external_id' => data_get($context, 'chat_external_id'),
                'sender_external_id' => data_get($context, 'sender_external_id'),
                'feedback_kind' => $kind,
                'feedback_phase' => $phase,
                'status' => 'pending',
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueFeedbackException($exception)) {
                throw $exception;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isFeedbackEligible(Event $event, array $context): bool
    {
        return $event->isActive()
            && $event->isModuleEnabled('live')
            && filled(data_get($context, 'provider_message_id'))
            && filled(data_get($context, 'chat_external_id'))
            && in_array(data_get($context, 'intake_source'), ['whatsapp_group', 'whatsapp_direct'], true);
    }

    private function rejectReplyEnabled(Event $event): bool
    {
        return (bool) data_get(
            $event->current_entitlements_json,
            'channels.whatsapp.feedback.reject_reply.enabled',
            false,
        );
    }

    private function rejectReplyMessage(Event $event): string
    {
        return (string) data_get(
            $event->current_entitlements_json,
            'channels.whatsapp.feedback.reject_reply.message',
            self::DEFAULT_REJECT_REPLY,
        );
    }

    private function isUniqueFeedbackException(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'wa_message_feedbacks_unique');
    }
}
