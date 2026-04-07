<?php

namespace App\Modules\Telegram\Services;

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Telegram\Clients\BotApi\TelegramBotApiClient;
use App\Modules\Telegram\Models\TelegramMessageFeedback;
use Illuminate\Database\QueryException;

class TelegramFeedbackAutomationService
{
    private const DETECTED_REACTION = "\u{1F44D}";
    private const PUBLISHED_REACTION = "\u{2764}\u{FE0F}";
    private const REJECTED_REACTION = "\u{1F6AB}";
    private const BLOCKED_REACTION = "\u{1F6AB}";
    private const DEFAULT_SESSION_ACTIVATED_REPLY = 'Sessao ativada para o evento {event_title}. Envie fotos, videos ou documentos aqui. Para sair, digite SAIR.';
    private const DEFAULT_SESSION_CLOSED_REPLY = 'Sessao encerrada para o evento {event_title}.';
    private const DEFAULT_BLOCKED_REPLY = 'Este remetente esta bloqueado para o evento {event_title}.';
    private const DEFAULT_REJECT_REPLY = 'Sua midia nao segue as diretrizes do evento.';

    public function __construct(
        private readonly TelegramBotApiClient $client,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function sendDetectedFeedback(
        Event $event,
        array $context,
        ?InboundMessage $inboundMessage = null,
    ): void {
        $this->sendChatAction(
            event: $event,
            context: $context,
            action: $this->chatActionFor((string) data_get($inboundMessage?->normalized_payload_json, 'message_type', $inboundMessage?->message_type ?? 'photo')),
            phase: 'detected',
            inboundMessage: $inboundMessage,
        );

        $this->sendReaction(
            event: $event,
            context: $context,
            phase: 'detected',
            emoji: self::DETECTED_REACTION,
            inboundMessage: $inboundMessage,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendSessionActivatedFeedback(
        Event $event,
        array $context,
    ): ?TelegramMessageFeedback {
        return $this->sendReply(
            event: $event,
            context: $context,
            phase: 'session_activated',
            message: $this->sessionActivatedReplyMessage($event),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendSessionClosedFeedback(
        Event $event,
        array $context,
    ): ?TelegramMessageFeedback {
        return $this->sendReply(
            event: $event,
            context: $context,
            phase: 'session_closed',
            message: $this->sessionClosedReplyMessage($event),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendPublishedFeedback(
        Event $event,
        array $context,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): ?TelegramMessageFeedback {
        return $this->sendReaction(
            event: $event,
            context: $context,
            phase: 'published',
            emoji: self::PUBLISHED_REACTION,
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
            isBig: true,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendBlockedFeedback(
        Event $event,
        array $context,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): void {
        $this->sendReaction(
            event: $event,
            context: $context,
            phase: 'blocked',
            emoji: self::BLOCKED_REACTION,
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );

        $this->sendReply(
            event: $event,
            context: $context,
            phase: 'blocked',
            message: $this->blockedReplyMessage($event),
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendRejectedFeedback(
        Event $event,
        array $context,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): void {
        $this->sendReaction(
            event: $event,
            context: $context,
            phase: 'rejected',
            emoji: self::REJECTED_REACTION,
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );

        $this->sendReply(
            event: $event,
            context: $context,
            phase: 'rejected',
            message: $this->rejectReplyMessage($event),
            inboundMessage: $inboundMessage,
            eventMedia: $eventMedia,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendChatAction(
        Event $event,
        array $context,
        string $action,
        string $phase,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): ?TelegramMessageFeedback {
        if (! $this->isFeedbackEligible($event, $context)) {
            return null;
        }

        $feedback = $this->reserveFeedback($event, $context, 'chat_action', $phase, $inboundMessage, $eventMedia);

        if ($feedback === null) {
            return null;
        }

        try {
            $this->client->sendChatAction((string) data_get($context, 'chat_external_id'), $action);

            $feedback->forceFill([
                'status' => 'sent',
                'chat_action' => $action,
                'attempted_at' => now(),
                'completed_at' => now(),
                'error_message' => null,
            ])->save();

            return $feedback;
        } catch (\Throwable $exception) {
            $this->markFailed($feedback, $exception);
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendReaction(
        Event $event,
        array $context,
        string $phase,
        string $emoji,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
        bool $isBig = false,
    ): ?TelegramMessageFeedback {
        if (! $this->isFeedbackEligible($event, $context)) {
            return null;
        }

        $feedback = $this->reserveFeedback($event, $context, 'reaction', $phase, $inboundMessage, $eventMedia);

        if ($feedback === null) {
            return null;
        }

        try {
            $this->client->setMessageReaction(
                (string) data_get($context, 'chat_external_id'),
                (string) data_get($context, 'provider_message_id'),
                $emoji,
                $isBig,
            );

            $feedback->forceFill([
                'status' => 'sent',
                'reaction_emoji' => $emoji,
                'attempted_at' => now(),
                'completed_at' => now(),
                'error_message' => null,
            ])->save();

            return $feedback;
        } catch (\Throwable $exception) {
            $this->markFailed($feedback, $exception);
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendReply(
        Event $event,
        array $context,
        string $phase,
        string $message,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): ?TelegramMessageFeedback {
        if (! $this->isFeedbackEligible($event, $context)) {
            return null;
        }

        $feedback = $this->reserveFeedback($event, $context, 'reply', $phase, $inboundMessage, $eventMedia);

        if ($feedback === null) {
            return null;
        }

        try {
            $this->client->sendMessage(
                (string) data_get($context, 'chat_external_id'),
                $message,
                (string) data_get($context, 'provider_message_id'),
            );

            $feedback->forceFill([
                'status' => 'sent',
                'reply_text' => $message,
                'attempted_at' => now(),
                'completed_at' => now(),
                'error_message' => null,
            ])->save();

            return $feedback;
        } catch (\Throwable $exception) {
            $this->markFailed($feedback, $exception);
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function reserveFeedback(
        Event $event,
        array $context,
        string $kind,
        string $phase,
        ?InboundMessage $inboundMessage = null,
        ?EventMedia $eventMedia = null,
    ): ?TelegramMessageFeedback {
        try {
            return TelegramMessageFeedback::query()->create([
                'event_id' => $event->id,
                'event_channel_id' => data_get($context, 'event_channel_id'),
                'inbound_message_id' => $inboundMessage?->id,
                'event_media_id' => $eventMedia?->id,
                'inbound_provider_message_id' => (string) data_get($context, 'provider_message_id'),
                'chat_external_id' => (string) data_get($context, 'chat_external_id'),
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

    private function markFailed(TelegramMessageFeedback $feedback, \Throwable $exception): void
    {
        $feedback->forceFill([
            'status' => 'failed',
            'attempted_at' => now(),
            'error_message' => $exception->getMessage(),
        ])->save();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isFeedbackEligible(Event $event, array $context): bool
    {
        return $event->isActive()
            && $event->isModuleEnabled('live')
            && data_get($context, 'intake_source') === 'telegram'
            && filled(data_get($context, 'event_channel_id'))
            && filled(data_get($context, 'provider_message_id'))
            && filled(data_get($context, 'chat_external_id'));
    }

    private function chatActionFor(string $messageType): string
    {
        return match ($messageType) {
            'video' => 'upload_video',
            'document' => 'upload_document',
            default => 'upload_photo',
        };
    }

    private function rejectReplyMessage(Event $event): string
    {
        return (string) data_get(
            $event->current_entitlements_json,
            'channels.telegram.feedback.reject_reply.message',
            self::DEFAULT_REJECT_REPLY,
        );
    }

    private function blockedReplyMessage(Event $event): string
    {
        return $this->interpolateEventTitle(
            (string) data_get(
                $event->current_entitlements_json,
                'channels.telegram.feedback.blocked.message',
                self::DEFAULT_BLOCKED_REPLY,
            ),
            $event,
        );
    }

    private function sessionActivatedReplyMessage(Event $event): string
    {
        return $this->interpolateEventTitle(
            (string) data_get(
                $event->current_entitlements_json,
                'channels.telegram.feedback.session_activated.message',
                self::DEFAULT_SESSION_ACTIVATED_REPLY,
            ),
            $event,
        );
    }

    private function sessionClosedReplyMessage(Event $event): string
    {
        return $this->interpolateEventTitle(
            (string) data_get(
                $event->current_entitlements_json,
                'channels.telegram.feedback.session_closed.message',
                self::DEFAULT_SESSION_CLOSED_REPLY,
            ),
            $event,
        );
    }

    private function interpolateEventTitle(string $template, Event $event): string
    {
        $eventTitle = trim((string) $event->title);

        return str_replace('{event_title}', $eventTitle !== '' ? $eventTitle : 'seu evento', $template);
    }

    private function isUniqueFeedbackException(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'telegram_message_feedbacks_unique');
    }
}
