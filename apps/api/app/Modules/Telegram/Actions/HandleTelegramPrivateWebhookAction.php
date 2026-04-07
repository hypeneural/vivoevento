<?php

namespace App\Modules\Telegram\Actions;

use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Services\EventMediaSenderBlacklistService;
use App\Modules\InboundMedia\Jobs\NormalizeInboundMessageJob;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Support\TelegramUpdateInspector;
use App\Modules\Telegram\Jobs\SendTelegramFeedbackJob;
use App\Modules\Telegram\Models\TelegramInboxSession;
use App\Modules\Telegram\Services\TelegramPrivateSessionService;
use Illuminate\Database\QueryException;

class HandleTelegramPrivateWebhookAction
{
    public function __construct(
        private readonly TelegramUpdateInspector $inspector,
        private readonly TelegramPrivateSessionService $sessions,
        private readonly EventMediaSenderBlacklistService $blacklists,
    ) {}

    public function execute(array $payload): array
    {
        $inspection = $this->inspector->inspect($payload);
        $providerUpdateId = $inspection['update_id'] ?? null;
        $updateType = $inspection['update_type'] ?? 'unknown';

        if (is_string($providerUpdateId) && $this->hasProcessedUpdate($providerUpdateId)) {
            return ['status' => 'duplicate'];
        }

        $chatType = $inspection['chat_type'] ?? null;
        $webhookLog = $this->createWebhookLog($payload, $inspection, $chatType);

        if (! $webhookLog->wasRecentlyCreated && $webhookLog->provider_update_id !== null) {
            return ['status' => 'duplicate'];
        }

        if ($updateType === 'my_chat_member') {
            return $this->handleMyChatMemberSignal($webhookLog, $inspection);
        }

        if ($chatType !== 'private') {
            return ['status' => 'ignored'];
        }

        $commandStatus = $this->sessions->handleTextCommand($webhookLog, $inspection);

        if ($commandStatus !== null) {
            if ($commandStatus === 'sender_blocked') {
                $this->dispatchBlockedFeedback($webhookLog, $inspection);
            }

            if (in_array($commandStatus, ['session_activated', 'session_closed'], true)) {
                $this->dispatchSessionFeedback($inspection, $commandStatus);
            }

            return ['status' => $commandStatus];
        }

        if ($this->isSupportedMedia($inspection)) {
            $session = $this->sessions->findActiveSession((string) $inspection['chat_id']);

            if (! $session) {
                $webhookLog->update([
                    'routing_status' => 'ignored',
                    'error_message' => 'no_active_session',
                ]);

                return ['status' => 'no_active_session'];
            }

            if ($this->blacklists->matchCandidates($session->event, [
                'external_id' => [
                    $inspection['sender_id'] ?? null,
                    $inspection['chat_id'] ?? null,
                    $session->sender_external_id,
                    $session->chat_external_id,
                ],
                'phone' => [],
                'lid' => [],
            ])) {
                $webhookLog->update([
                    'event_channel_id' => $session->event_channel_id,
                    'routing_status' => 'blocked',
                    'error_message' => 'sender_blacklisted',
                ]);

                SendTelegramFeedbackJob::dispatch(
                    eventId: $session->event_id,
                    context: $this->feedbackContext($inspection, $session),
                    phase: 'blocked',
                );

                return ['status' => 'sender_blocked'];
            }

            $canonicalPayload = $this->canonicalPayload($payload, $inspection, $session);

            $webhookLog->update([
                'event_channel_id' => $session->event_channel_id,
                'payload_json' => $canonicalPayload,
            ]);

            $this->sessions->touchSession($session, $inspection);

            SendTelegramFeedbackJob::dispatch(
                eventId: $session->event_id,
                context: $canonicalPayload['_event_context'],
                phase: 'detected',
            );
        }

        NormalizeInboundMessageJob::dispatch($webhookLog->id);

        return ['status' => 'received'];
    }

    private function hasProcessedUpdate(string $providerUpdateId): bool
    {
        return ChannelWebhookLog::query()
            ->where('provider', 'telegram')
            ->where('provider_update_id', $providerUpdateId)
            ->exists();
    }

    private function createWebhookLog(array $payload, array $inspection, ?string $chatType): ChannelWebhookLog
    {
        $attributes = [
            'provider' => 'telegram',
            'provider_update_id' => $inspection['update_id'] ?? null,
        ];

        $detectedType = $inspection['update_type'] === 'my_chat_member'
            ? 'my_chat_member'
            : ($inspection['message_type'] ?? 'unknown');

        $values = [
            'message_id' => $inspection['message_id'] ?? null,
            'detected_type' => $detectedType,
            'routing_status' => $chatType === 'private' ? 'received' : 'ignored',
            'payload_json' => $payload,
            'error_message' => $chatType === 'private'
                ? null
                : ($chatType === null ? 'unsupported_update_type' : 'out_of_scope_chat_type'),
        ];

        if ($attributes['provider_update_id'] === null) {
            return ChannelWebhookLog::query()->create(array_merge($attributes, $values));
        }

        try {
            return ChannelWebhookLog::query()->firstOrCreate($attributes, $values);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateException($exception)) {
                throw $exception;
            }

            return ChannelWebhookLog::query()
                ->where($attributes)
                ->firstOrFail();
        }
    }

    private function isDuplicateException(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }

    private function isSupportedMedia(array $inspection): bool
    {
        return in_array($inspection['message_type'] ?? null, ['photo', 'video', 'document'], true);
    }

    private function dispatchSessionFeedback(array $inspection, string $phase): void
    {
        $chatId = $inspection['chat_id'] ?? null;

        if (! is_string($chatId)) {
            return;
        }

        $session = $phase === 'session_activated'
            ? $this->sessions->findActiveSession($chatId)
            : $this->sessions->findLatestSession($chatId);

        if (! $session) {
            return;
        }

        SendTelegramFeedbackJob::dispatch(
            eventId: $session->event_id,
            context: $this->feedbackContext($inspection, $session),
            phase: $phase === 'sender_blocked' ? 'blocked' : $phase,
        );
    }

    private function feedbackContext(array $inspection, TelegramInboxSession $session): array
    {
        return [
            'event_id' => $session->event_id,
            'event_channel_id' => $session->event_channel_id,
            'intake_source' => 'telegram',
            'source_subtype' => 'direct',
            'inbox_session_id' => $session->id,
            'provider_update_id' => $inspection['update_id'] ?? null,
            'provider_message_id' => $inspection['message_id'] ?? null,
            'chat_external_id' => $inspection['chat_id'] ?? $session->chat_external_id,
            'sender_external_id' => $inspection['sender_id'] ?? $session->sender_external_id,
            'sender_name' => $inspection['sender_name'] ?? $session->sender_name,
        ];
    }

    private function dispatchBlockedFeedback(ChannelWebhookLog $webhookLog, array $inspection): void
    {
        $eventChannelId = $webhookLog->event_channel_id;

        if (! is_int($eventChannelId)) {
            return;
        }

        $channel = EventChannel::query()
            ->with('event')
            ->find($eventChannelId);

        if (! $channel?->event) {
            return;
        }

        SendTelegramFeedbackJob::dispatch(
            eventId: $channel->event->id,
            context: [
                'event_id' => $channel->event->id,
                'event_channel_id' => $channel->id,
                'intake_source' => 'telegram',
                'source_subtype' => 'direct',
                'provider_update_id' => $inspection['update_id'] ?? null,
                'provider_message_id' => $inspection['message_id'] ?? null,
                'chat_external_id' => $inspection['chat_id'] ?? null,
                'sender_external_id' => $inspection['sender_id'] ?? null,
                'sender_name' => $inspection['sender_name'] ?? null,
            ],
            phase: 'blocked',
        );
    }

    private function canonicalPayload(array $payload, array $inspection, TelegramInboxSession $session): array
    {
        return [
            'provider' => 'telegram',
            'provider_update_id' => $inspection['update_id'] ?? null,
            'provider_message_id' => $inspection['message_id'] ?? null,
            'message_type' => $inspection['message_type'] ?? 'unknown',
            'occurred_at' => $inspection['occurred_at'] ?? null,
            'chat_external_id' => $inspection['chat_id'] ?? null,
            'chat_type' => $inspection['chat_type'] ?? null,
            'media_group_id' => $inspection['media_group_id'] ?? null,
            'sender_external_id' => $inspection['sender_id'] ?? null,
            'sender_name' => $inspection['sender_name'] ?? $session->sender_name,
            'body_text' => $inspection['text'] ?? null,
            'caption' => $inspection['caption'] ?? null,
            'entities' => $inspection['entities'] ?? [],
            'caption_entities' => $inspection['caption_entities'] ?? [],
            'from_me' => false,
            'media' => [
                'download_strategy' => 'telegram_file',
                'file_id' => $inspection['file_id'] ?? null,
                'file_unique_id' => $inspection['file_unique_id'] ?? null,
                'file_path' => null,
                'mime_type' => $inspection['mime_type'] ?? null,
                'file_name' => $inspection['file_name'] ?? null,
                'width' => $inspection['width'] ?? null,
                'height' => $inspection['height'] ?? null,
                'duration' => $inspection['duration'] ?? null,
                'file_size' => $inspection['file_size'] ?? null,
            ],
            '_event_context' => [
                'event_id' => $session->event_id,
                'event_channel_id' => $session->event_channel_id,
                'intake_source' => 'telegram',
                'source_subtype' => 'direct',
                'inbox_session_id' => $session->id,
                'provider_update_id' => $inspection['update_id'] ?? null,
                'provider_message_id' => $inspection['message_id'] ?? null,
                'chat_external_id' => $inspection['chat_id'] ?? null,
                'sender_external_id' => $inspection['sender_id'] ?? null,
                'sender_name' => $inspection['sender_name'] ?? $session->sender_name,
                'caption' => $inspection['caption'] ?? $inspection['text'] ?? null,
                'media_group_id' => $inspection['media_group_id'] ?? null,
                'from_me' => false,
                'media_url' => null,
            ],
            'provider_context_json' => [
                'update_id' => $inspection['update_id'] ?? null,
                'message_id' => $inspection['message_id'] ?? null,
                'chat_type' => $inspection['chat_type'] ?? null,
            ],
            '_provider_payload' => $payload,
        ];
    }

    private function handleMyChatMemberSignal(ChannelWebhookLog $webhookLog, array $inspection): array
    {
        if (($inspection['chat_type'] ?? null) !== 'private') {
            $webhookLog->update([
                'routing_status' => 'ignored',
                'error_message' => 'out_of_scope_chat_type',
            ]);

            return ['status' => 'ignored'];
        }

        $session = is_string($inspection['chat_id'] ?? null)
            ? $this->sessions->findLatestSession((string) $inspection['chat_id'])
            : null;
        $signal = $this->resolveMyChatMemberSignal($inspection);

        $webhookLog->update([
            'event_channel_id' => $session?->event_channel_id,
            'routing_status' => 'operational_signal',
            'error_message' => $signal,
        ]);

        return ['status' => 'operational_signal'];
    }

    private function resolveMyChatMemberSignal(array $inspection): string
    {
        $oldStatus = $inspection['my_chat_member_old_status'] ?? null;
        $newStatus = $inspection['my_chat_member_new_status'] ?? null;

        if ($newStatus === 'kicked') {
            return 'bot_blocked_by_user';
        }

        if ($oldStatus === 'kicked' && in_array($newStatus, ['member', 'administrator'], true)) {
            return 'bot_unblocked_by_user';
        }

        return 'bot_chat_member_status_changed';
    }
}
