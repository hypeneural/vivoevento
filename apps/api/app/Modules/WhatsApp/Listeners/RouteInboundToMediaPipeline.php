<?php

namespace App\Modules\WhatsApp\Listeners;

use App\Modules\InboundMedia\Jobs\ProcessInboundWebhookJob;
use App\Modules\Events\Services\EventMediaSenderBlacklistService;
use App\Modules\WhatsApp\Enums\GroupBindingType;
use App\Modules\WhatsApp\Events\WhatsAppMessageReceived;
use App\Modules\WhatsApp\Services\WhatsAppDirectIntakeSessionService;
use App\Modules\WhatsApp\Services\WhatsAppFeedbackAutomationService;
use App\Modules\WhatsApp\Services\WhatsAppGroupActivationService;
use App\Modules\WhatsApp\Services\WhatsAppInboundEventContextResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Commercially-aware bridge between WhatsApp inbound events and the media intake pipeline.
 *
 * Responsibilities:
 * - handle direct-message intake commands (code activation / sair)
 * - resolve whether the inbound message belongs to an event context
 * - forward only eligible media payloads to the InboundMedia pipeline
 */
class RouteInboundToMediaPipeline implements ShouldQueue
{
    public string $queue = 'webhooks';

    public function handle(
        WhatsAppMessageReceived $event,
    ): void {
        $directSessions = app(WhatsAppDirectIntakeSessionService::class);
        $groupActivation = app(WhatsAppGroupActivationService::class);
        $contextResolver = app(WhatsAppInboundEventContextResolver::class);
        $blacklists = app(EventMediaSenderBlacklistService::class);
        $feedback = app(WhatsAppFeedbackAutomationService::class);

        if (($event->normalized->fromMe ?? false) === true) {
            Log::channel('whatsapp')->info('Inbound intake ignored because the message is from the instance itself', [
                'message_id' => $event->message->id,
                'provider_message_id' => $event->normalized->messageId,
            ]);

            return;
        }

        if ($event->normalized->messageType === 'text') {
            $handledGroupActivation = $groupActivation->handleTextMessage(
                $event->message->instance,
                $event->message,
                $event->normalized,
            );

            if ($handledGroupActivation) {
                Log::channel('whatsapp')->info('Inbound WhatsApp text handled as a group activation command', [
                    'message_id' => $event->message->id,
                    'provider_message_id' => $event->normalized->messageId,
                    'chat_id' => $event->normalized->chatId,
                ]);

                return;
            }

            $handled = $directSessions->handleTextMessage(
                $event->message->instance,
                $event->message,
                $event->normalized,
            );

            if ($handled) {
                Log::channel('whatsapp')->info('Inbound WhatsApp text handled as a direct intake command', [
                    'message_id' => $event->message->id,
                    'provider_message_id' => $event->normalized->messageId,
                    'chat_id' => $event->normalized->chatId,
                ]);

                return;
            }
        }

        if (! $event->hasMedia()) {
            return;
        }

        if ($event->normalized->messageType === 'sticker') {
            Log::channel('whatsapp')->info('Inbound sticker ignored for event media intake because stickers are not treated as gallery media', [
                'message_id' => $event->message->id,
                'provider_message_id' => $event->normalized->messageId,
                'chat_id' => $event->normalized->chatId,
                'sender_external_id' => $event->normalized->senderExternalId(),
                'sender_phone' => $event->normalized->senderPhone,
                'is_group' => $event->normalized->isFromGroup(),
                'mime_type' => $event->normalized->mimeType,
            ]);

            return;
        }

        $captureTarget = $event->normalized->messageType === 'audio'
            ? 'event_audio'
            : 'event_media';

        $context = $contextResolver->resolve(
            $event->message->instance,
            $event->normalized,
            $event->groupBinding,
        );

        if (! $context) {
            if ($event->normalized->isFromGroup()) {
                Log::channel('whatsapp')->warning('Inbound group media ignored because no eligible event context was resolved', [
                    'message_id' => $event->message->id,
                    'provider_message_id' => $event->normalized->messageId,
                    'chat_id' => $event->normalized->chatId,
                    'group_id' => $event->normalized->groupId,
                    'type' => $event->normalized->messageType,
                ]);

                return;
            }

            $missingSessionResult = $directSessions->handleMediaWithoutActiveSession(
                $event->message->instance,
                $event->normalized,
            );

            Log::channel('whatsapp')->warning('Inbound direct media ignored because no active intake session was resolved', [
                'message_id' => $event->message->id,
                'provider_message_id' => $event->normalized->messageId,
                'chat_id' => $event->normalized->chatId,
                'sender_external_id' => $event->normalized->senderExternalId(),
                'sender_phone' => $event->normalized->senderPhone,
                'type' => $event->normalized->messageType,
                'missing_session' => $missingSessionResult,
            ]);

            return;
        }

        if ($blacklists->matchNormalized($context->event, $event->normalized)) {
            $feedback->sendRejectedFeedback($context->event, $event->message->instance, [
                'provider_message_id' => $event->normalized->messageId,
                'chat_external_id' => $event->normalized->chatId,
                'sender_external_id' => $event->normalized->senderExternalId(),
                'intake_source' => $context->intakeSource,
            ], phase: 'blocked');

            return;
        }

        if ($context->groupBinding && $context->groupBinding->binding_type !== GroupBindingType::EventGallery) {
            return;
        }

        $payload = array_merge($event->normalized->rawPayload, [
            'message_type' => $event->normalized->messageType,
            'mime_type' => $event->normalized->mimeType,
            '_event_context' => array_merge($context->toArray(), [
                'provider_message_id' => $event->normalized->messageId,
                'chat_external_id' => $event->normalized->chatId,
                'group_external_id' => $event->normalized->groupId,
                'sender_external_id' => $event->normalized->senderExternalId(),
                'sender_phone' => $event->normalized->senderPhone,
                'sender_lid' => $event->normalized->participantLid,
                'sender_name' => $event->normalized->senderName,
                'sender_avatar_url' => data_get($event->normalized->rawPayload, 'senderPhoto'),
                'from_me' => $event->normalized->fromMe,
                'caption' => $event->normalized->caption,
                'media_url' => $event->normalized->mediaUrl,
                'message_type' => $event->normalized->messageType,
                'mime_type' => $event->normalized->mimeType,
                'capture_target' => $captureTarget,
                'trace_id' => data_get($event->normalized->rawPayload, '_trace_id'),
            ]),
        ]);

        Log::channel('whatsapp')->info(
            $captureTarget === 'event_audio'
                ? 'Routing inbound audio to the event capture pipeline'
                : 'Routing inbound media to gallery pipeline',
            [
            'message_id' => $event->message->id,
            'event_id' => $context->event->id,
            'event_channel_id' => $context->eventChannel->id,
            'intake_source' => $context->intakeSource,
            'media_url' => $event->normalized->mediaUrl,
            'type' => $event->normalized->messageType,
            'capture_target' => $captureTarget,
            'trace_id' => data_get($event->normalized->rawPayload, '_trace_id'),
            ],
        );

        $feedback->sendDetectedReaction($context->event, $event->message->instance, [
            'provider_message_id' => $event->normalized->messageId,
            'chat_external_id' => $event->normalized->chatId,
            'sender_external_id' => $event->normalized->senderExternalId(),
            'intake_source' => $context->intakeSource,
        ]);

        ProcessInboundWebhookJob::dispatch(
            $event->normalized->providerKey,
            $payload,
        );
    }
}
