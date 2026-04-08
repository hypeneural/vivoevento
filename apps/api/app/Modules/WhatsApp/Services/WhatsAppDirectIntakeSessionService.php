<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Services\EventMediaSenderBlacklistService;
use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Models\WhatsAppInboxSession;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class WhatsAppDirectIntakeSessionService
{
    private const MISSING_SESSION_NOTICE_COOLDOWN_MINUTES = 5;

    public function __construct(
        private readonly WhatsAppMessagingService $messagingService,
        private readonly EventMediaSenderBlacklistService $blacklists,
        private readonly WhatsAppFeedbackAutomationService $feedback,
        private readonly WhatsAppEventInstanceEligibilityService $instanceEligibility,
    ) {}

    public function handleTextMessage(
        WhatsAppInstance $instance,
        WhatsAppMessage $message,
        NormalizedInboundMessageData $normalized,
    ): bool {
        if ($normalized->isFromGroup() || $normalized->messageType !== 'text' || ($normalized->fromMe ?? false)) {
            return false;
        }

        $text = $normalized->normalizedText();

        if ($text === null) {
            return false;
        }

        $activeSession = $this->findActiveSession($instance, $normalized);

        if ($activeSession !== null && $this->isExitCommand($text)) {
            $this->closeSession($activeSession, $normalized->messageId);
            $this->sendReply(
                $instance,
                $normalized,
                $this->buildSessionClosedMessage($activeSession->event?->title),
            );

            return true;
        }

        $candidateCode = $this->extractCandidateCode($text);

        if ($candidateCode === null) {
            return false;
        }

        if ($activeSession !== null) {
            $activeSession->loadMissing(['event', 'channel']);

            $activeCode = trim((string) ($activeSession->channel?->external_id ?? ''));

            if ($activeCode !== '' && strcasecmp($activeCode, $candidateCode) === 0) {
                $ttlMinutes = max(1, (int) data_get($activeSession->channel?->config_json, 'session_ttl_minutes', 180));

                $activeSession->update([
                    'last_inbound_provider_message_id' => $normalized->messageId,
                    'last_interaction_at' => now(),
                    'expires_at' => now()->addMinutes($ttlMinutes),
                ]);

                $this->sendReply(
                    $instance,
                    $normalized,
                    $this->buildSessionAlreadyActiveMessage($activeSession->event?->title),
                );

                return true;
            }
        }

        $channel = $this->findDirectChannelByCode($instance, $candidateCode);

        if (! $channel) {
            return false;
        }

        $event = $channel->event;

        if (! $event || ! $this->eventAllowsDirectIntake($event, $instance)) {
            return false;
        }

        if ($this->blacklists->matchNormalized($event, $normalized)) {
            $this->feedback->sendRejectedFeedback($event, $instance, [
                'provider_message_id' => $normalized->messageId,
                'chat_external_id' => $normalized->chatId,
                'sender_external_id' => $normalized->senderExternalId(),
                'intake_source' => 'whatsapp_direct',
            ], phase: 'blocked');

            return true;
        }

        $ttlMinutes = max(1, (int) data_get($channel->config_json, 'session_ttl_minutes', 180));
        $senderExternalId = $normalized->senderExternalId();

        WhatsAppInboxSession::query()
            ->active()
            ->where('instance_id', $instance->id)
            ->where('sender_external_id', $senderExternalId)
            ->where('event_id', '!=', $event->id)
            ->update([
                'status' => 'closed',
                'closed_at' => now(),
                'last_inbound_provider_message_id' => $normalized->messageId,
            ]);

        $session = WhatsAppInboxSession::query()
            ->where('instance_id', $instance->id)
            ->where('sender_external_id', $senderExternalId)
            ->where('event_id', $event->id)
            ->where('event_channel_id', $channel->id)
            ->latest('id')
            ->first();

        if (! $session) {
            $session = new WhatsAppInboxSession([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'instance_id' => $instance->id,
                'sender_external_id' => $senderExternalId,
                'chat_external_id' => $normalized->chatId,
            ]);
        }

        $session->fill([
            'sender_phone' => $normalized->senderPhone,
            'sender_lid' => $normalized->participantLid,
            'sender_name' => $normalized->senderName,
            'status' => 'active',
            'activated_by_provider_message_id' => $normalized->messageId,
            'last_inbound_provider_message_id' => $normalized->messageId,
            'activated_at' => now(),
            'last_interaction_at' => now(),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'closed_at' => null,
            'metadata_json' => [
                'media_inbox_code' => $candidateCode,
            ],
        ]);
        $session->save();

        $this->sendReply(
            $instance,
            $normalized,
            $this->buildSessionActivatedMessage($event->title),
        );

        return true;
    }

    public function findActiveSession(
        WhatsAppInstance $instance,
        NormalizedInboundMessageData $normalized,
    ): ?WhatsAppInboxSession {
        $senderExternalId = $normalized->senderExternalId();

        return WhatsAppInboxSession::query()
            ->with(['event.modules', 'channel'])
            ->active()
            ->where('instance_id', $instance->id)
            ->where('sender_external_id', $senderExternalId)
            ->latest('id')
            ->first();
    }

    public function touchSession(WhatsAppInboxSession $session, NormalizedInboundMessageData $normalized): void
    {
        if ($session->status !== 'active') {
            return;
        }

        $expiresAt = $session->expires_at instanceof Carbon
            ? $session->expires_at
            : null;

        $session->update([
            'last_inbound_provider_message_id' => $normalized->messageId,
            'last_interaction_at' => now(),
            'expires_at' => $expiresAt?->greaterThan(now())
                ? $expiresAt
                : now()->addMinutes(5),
        ]);
    }

    /**
     * @return array{
     *     matched: bool,
     *     notice_sent: bool,
     *     reason: string,
     *     session_id?: int,
     *     event_id?: int,
     *     event_channel_id?: int,
     *     session_status?: string,
     *     expires_at?: string|null,
     *     activation_code?: string|null
     * }
     */
    public function handleMediaWithoutActiveSession(
        WhatsAppInstance $instance,
        NormalizedInboundMessageData $normalized,
    ): array {
        if ($normalized->isFromGroup() || ! $normalized->hasMedia() || ($normalized->fromMe ?? false)) {
            return [
                'matched' => false,
                'notice_sent' => false,
                'reason' => 'not_applicable',
            ];
        }

        $session = $this->findLatestSession($instance, $normalized);

        if (! $session) {
            return [
                'matched' => false,
                'notice_sent' => false,
                'reason' => 'no_known_session',
            ];
        }

        $session->loadMissing(['event', 'channel']);

        if ($this->isSessionActive($session)) {
            return [
                'matched' => false,
                'notice_sent' => false,
                'reason' => 'active_session',
            ];
        }

        $reason = $session->status === 'closed'
            ? 'closed_session'
            : 'expired_session';

        $noticeSent = false;

        if (! Cache::has($this->missingSessionNoticeCooldownKey($instance, $normalized))) {
            $this->sendReply(
                $instance,
                $normalized,
                $this->buildSessionReactivationMessage(
                    $session->event?->title,
                    $session->channel?->external_id,
                ),
            );

            Cache::put(
                $this->missingSessionNoticeCooldownKey($instance, $normalized),
                true,
                now()->addMinutes(self::MISSING_SESSION_NOTICE_COOLDOWN_MINUTES),
            );

            $noticeSent = true;
        }

        return [
            'matched' => true,
            'notice_sent' => $noticeSent,
            'reason' => $reason,
            'session_id' => $session->id,
            'event_id' => $session->event_id,
            'event_channel_id' => $session->event_channel_id,
            'session_status' => (string) $session->status,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'activation_code' => $session->channel?->external_id,
        ];
    }

    public function findLatestSession(
        WhatsAppInstance $instance,
        NormalizedInboundMessageData $normalized,
    ): ?WhatsAppInboxSession {
        return WhatsAppInboxSession::query()
            ->with(['event', 'channel'])
            ->where('instance_id', $instance->id)
            ->where('sender_external_id', $normalized->senderExternalId())
            ->latest('id')
            ->first();
    }

    private function findDirectChannelByCode(WhatsAppInstance $instance, string $candidateCode): ?EventChannel
    {
        return EventChannel::query()
            ->with(['event.modules'])
            ->where('channel_type', ChannelType::WhatsAppDirect->value)
            ->where('status', 'active')
            ->where('provider', $instance->providerKeyValue())
            ->where('external_id', $candidateCode)
            ->get()
            ->first(function (EventChannel $channel) use ($instance) {
                $event = $channel->event;

                return $event !== null
                    && $this->instanceEligibility->allowsInboundOnInstance($event, $instance);
            });
    }

    private function eventAllowsDirectIntake(Event $event, WhatsAppInstance $instance): bool
    {
        $event->loadMissing('modules');

        return $event->isActive()
            && $event->isModuleEnabled('live')
            && $this->instanceEligibility->allowsInboundOnInstance($event, $instance)
            && (bool) data_get($event->current_entitlements_json, 'channels.whatsapp_direct.enabled', false);
    }

    private function closeSession(WhatsAppInboxSession $session, string $providerMessageId): void
    {
        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
            'last_inbound_provider_message_id' => $providerMessageId,
            'last_interaction_at' => now(),
        ]);
    }

    private function sendReply(
        WhatsAppInstance $instance,
        NormalizedInboundMessageData $normalized,
        string $message,
    ): void {
        $this->messagingService->sendText(
            $instance,
            new SendTextData(
                phone: $normalized->senderPhone ?? $normalized->chatId,
                message: $message,
                messageId: $normalized->messageId,
            ),
        );
    }

    private function buildSessionActivatedMessage(string $eventTitle): string
    {
        return implode("\n", [
            '*EventoVivo*',
            '',
            "✨ Vinculo realizado: {$eventTitle}",
            '',
            '📸 Agora voce pode enviar suas fotos e videos.',
            '🚪 Para encerrar, digite Sair a qualquer momento.',
        ]);
    }

    private function buildSessionClosedMessage(?string $eventTitle): string
    {
        $titleLine = $eventTitle !== null && $eventTitle !== ''
            ? "Sessao encerrada: {$eventTitle}."
            : 'Sessao encerrada.';

        return implode("\n", [
            '*EventoVivo*',
            '',
            $titleLine,
            '',
            'Se quiser voltar a enviar midias, mande o codigo novamente.',
        ]);
    }

    private function buildSessionAlreadyActiveMessage(?string $eventTitle): string
    {
        $titleLine = $eventTitle !== null && $eventTitle !== ''
            ? "Voce ja esta vinculado ao evento {$eventTitle}."
            : 'Voce ja esta com uma sessao ativa para envio de midias.';

        return implode("\n", [
            '*EventoVivo*',
            '',
            $titleLine,
            '',
            'Pode continuar enviando fotos e videos normalmente.',
        ]);
    }

    private function buildSessionReactivationMessage(?string $eventTitle, ?string $code): string
    {
        $titleLine = $eventTitle !== null && $eventTitle !== ''
            ? "Seu acesso para {$eventTitle} nao esta mais ativo."
            : 'Seu acesso para envio de midias nao esta mais ativo.';

        $instructionLine = $code !== null && $code !== ''
            ? "Envie {$code} novamente para continuar enviando fotos e videos."
            : 'Envie o codigo novamente para continuar enviando fotos e videos.';

        return implode("\n", [
            '*EventoVivo*',
            '',
            $titleLine,
            '',
            $instructionLine,
        ]);
    }

    private function isSessionActive(WhatsAppInboxSession $session): bool
    {
        if ($session->status !== 'active' || $session->closed_at !== null) {
            return false;
        }

        return $session->expires_at === null || $session->expires_at->isFuture();
    }

    private function missingSessionNoticeCooldownKey(
        WhatsAppInstance $instance,
        NormalizedInboundMessageData $normalized,
    ): string {
        return implode(':', [
            'whatsapp',
            'direct-intake',
            'missing-session-notice',
            $instance->id,
            $normalized->senderExternalId(),
        ]);
    }

    private function isExitCommand(string $text): bool
    {
        return mb_strtoupper(trim($text)) === 'SAIR';
    }

    private function extractCandidateCode(string $text): ?string
    {
        $normalized = mb_strtoupper(preg_replace('/\s+/', '', trim($text)) ?? '');

        if ($normalized === '' || $normalized === 'SAIR') {
            return null;
        }

        foreach ([
            '/^#ATIVAR#([A-Z0-9_-]{4,64})$/',
            '/^#([A-Z0-9_-]{4,64})$/',
            '/^([A-Z0-9_-]{4,64})$/',
        ] as $pattern) {
            if (preg_match($pattern, $normalized, $matches) === 1) {
                return $matches[1] ?? $normalized;
            }
        }

        return null;
    }
}
