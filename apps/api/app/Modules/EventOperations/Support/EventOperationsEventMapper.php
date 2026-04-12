<?php

namespace App\Modules\EventOperations\Support;

use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Telegram\Models\TelegramMessageFeedback;
use App\Modules\Wall\Events\WallDiagnosticsUpdated;
use App\Modules\Wall\Events\WallMediaPublished;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class EventOperationsEventMapper
{
    public function __construct(
        private readonly EventOperationsAttentionPriority $priority,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function fromInboundMessage(InboundMessage $message): ?array
    {
        if (! $message->event_id || $message->from_me) {
            return null;
        }

        $occurredAt = $message->received_at ?? now();
        $intakeSource = (string) data_get($message->normalized_payload_json, '_event_context.intake_source', $message->provider);

        return $this->mappedPayload(
            eventKey: 'media.card.arrived',
            severity: 'info',
            urgency: 'low',
            payload: [
                'event_media_id' => null,
                'inbound_message_id' => $message->id,
                'station_key' => 'intake',
                'title' => 'Nova midia recebida',
                'summary' => trim(sprintf(
                    '%s entrou via %s.',
                    $message->sender_name ?: 'Convidado',
                    $intakeSource !== '' ? $intakeSource : 'canal conectado',
                )),
                'payload_json' => [
                    'provider' => $message->provider,
                    'intake_source' => $intakeSource,
                    'message_type' => $message->message_type,
                    'sender_name' => $message->sender_name,
                    'chat_external_id' => $message->chat_external_id,
                ],
                'animation_hint' => 'intake_pulse',
                'station_load' => 0.20,
                'queue_depth' => 1,
                'render_group' => 'intake',
                'dedupe_window_key' => $this->dedupeWindowKey('intake', $occurredAt, $intakeSource),
                'correlation_key' => $message->trace_id ?: "inbound:{$message->id}",
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromEventMediaCreated(EventMedia $media): ?array
    {
        if (! $media->event_id) {
            return null;
        }

        $occurredAt = $media->created_at ?? now();

        return $this->mappedPayload(
            eventKey: 'media.download.completed',
            severity: 'info',
            urgency: 'low',
            payload: [
                'event_media_id' => $media->id,
                'inbound_message_id' => $media->inbound_message_id,
                'station_key' => 'download',
                'title' => 'Midia materializada',
                'summary' => 'A recepcao materializou a midia no evento.',
                'payload_json' => $this->mediaPayload($media),
                'animation_hint' => 'download_complete',
                'station_load' => 0.28,
                'queue_depth' => 0,
                'render_group' => 'processing',
                'dedupe_window_key' => $this->dedupeWindowKey('download', $occurredAt, $media->event_id),
                'correlation_key' => "media:{$media->id}",
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromMediaVariantsGenerated(MediaVariantsGenerated $event): ?array
    {
        $media = $event->resolveMedia();

        if (! $media) {
            return null;
        }

        $occurredAt = $media->updated_at ?? now();

        return $this->mappedPayload(
            eventKey: 'media.variants.generated',
            severity: 'info',
            urgency: 'low',
            payload: [
                'event_media_id' => $media->id,
                'inbound_message_id' => $media->inbound_message_id,
                'station_key' => 'variants',
                'title' => 'Variantes prontas',
                'summary' => 'A bancada tecnica concluiu as variantes da midia.',
                'payload_json' => $this->mediaPayload($media),
                'animation_hint' => 'variants_ready',
                'station_load' => 0.35,
                'queue_depth' => 0,
                'render_group' => 'processing',
                'dedupe_window_key' => $this->dedupeWindowKey('variants', $occurredAt, $media->event_id),
                'correlation_key' => "media:{$media->id}",
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromMediaPublishedToModeration(MediaPublished $event): ?array
    {
        $media = $event->resolveMedia();

        if (! $media) {
            return null;
        }

        $stationKey = $this->moderationStationFor($media, approved: true);
        $occurredAt = $media->published_at ?? $media->updated_at ?? now();

        return $this->mappedPayload(
            eventKey: 'media.moderation.approved',
            severity: 'info',
            urgency: 'low',
            payload: [
                'event_media_id' => $media->id,
                'inbound_message_id' => $media->inbound_message_id,
                'station_key' => $stationKey,
                'title' => 'Midia aprovada',
                'summary' => 'A moderacao liberou a midia para publicacao.',
                'payload_json' => $this->mediaPayload($media),
                'animation_hint' => 'decision_resolved',
                'station_load' => 0.20,
                'queue_depth' => 0,
                'render_group' => 'review',
                'dedupe_window_key' => $this->dedupeWindowKey('moderation-approved', $occurredAt, $media->event_id),
                'correlation_key' => "media:{$media->id}",
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromMediaRejected(MediaRejected $event): ?array
    {
        $media = $event->resolveMedia();

        if (! $media) {
            return null;
        }

        $stationKey = $this->moderationStationFor($media, approved: false);
        $occurredAt = $media->updated_at ?? now();

        return $this->mappedPayload(
            eventKey: 'media.moderation.rejected',
            severity: 'warning',
            urgency: 'high',
            payload: [
                'event_media_id' => $media->id,
                'inbound_message_id' => $media->inbound_message_id,
                'station_key' => $stationKey,
                'title' => 'Midia rejeitada',
                'summary' => 'A moderacao bloqueou a midia no fluxo.',
                'payload_json' => $this->mediaPayload($media),
                'animation_hint' => $stationKey === 'safety' ? 'safety_warning' : 'review_warning',
                'station_load' => 0.78,
                'queue_depth' => 1,
                'render_group' => 'review',
                'dedupe_window_key' => $this->dedupeWindowKey('moderation-rejected', $occurredAt, $media->event_id),
                'correlation_key' => "media:{$media->id}",
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromMediaPublishedToGallery(MediaPublished $event): ?array
    {
        $media = $event->resolveMedia();

        if (! $media) {
            return null;
        }

        $occurredAt = $media->published_at ?? $media->updated_at ?? now();

        return $this->mappedPayload(
            eventKey: 'media.published.gallery',
            severity: 'info',
            urgency: 'normal',
            payload: [
                'event_media_id' => $media->id,
                'inbound_message_id' => $media->inbound_message_id,
                'station_key' => 'gallery',
                'title' => 'Midia publicada na galeria',
                'summary' => 'A galeria recebeu uma nova midia publicada.',
                'payload_json' => $this->mediaPayload($media),
                'animation_hint' => 'gallery_glow',
                'station_load' => 0.34,
                'queue_depth' => 0,
                'render_group' => 'publishing',
                'dedupe_window_key' => $this->dedupeWindowKey('gallery', $occurredAt, $media->event_id),
                'correlation_key' => "media:{$media->id}",
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromWallMediaPublished(WallMediaPublished $event): ?array
    {
        $settings = EventWallSetting::query()
            ->where('wall_code', $event->wallCode)
            ->first();

        if (! $settings) {
            return null;
        }

        return $this->mappedPayload(
            eventKey: 'media.published.wall',
            severity: 'info',
            urgency: 'normal',
            payload: [
                'event_media_id' => data_get($event->payload, 'id'),
                'inbound_message_id' => null,
                'station_key' => 'wall',
                'title' => 'Midia entrou no telao',
                'summary' => 'O wall recebeu uma nova midia elegivel.',
                'payload_json' => [
                    'current_item_id' => data_get($event->payload, 'id'),
                    'media_type' => data_get($event->payload, 'type'),
                    'preview_url' => data_get($event->payload, 'url'),
                    'caption' => data_get($event->payload, 'caption'),
                ],
                'animation_hint' => 'wall_spotlight',
                'station_load' => 0.26,
                'queue_depth' => 0,
                'render_group' => 'wall',
                'dedupe_window_key' => $this->dedupeWindowKey('wall-published', now(), $settings->event_id),
                'correlation_key' => 'wall-media:' . (string) data_get($event->payload, 'id'),
                'occurred_at' => now()->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromWallDiagnosticsUpdated(WallDiagnosticsUpdated $event): ?array
    {
        $summary = $event->payload;
        $offlinePlayers = (int) ($summary['offline_players'] ?? 0);
        $degradedPlayers = (int) ($summary['degraded_players'] ?? 0);
        $healthStatus = (string) ($summary['health_status'] ?? 'idle');
        $occurredAt = isset($summary['updated_at'])
            ? Carbon::parse((string) $summary['updated_at'])
            : now();

        $severity = $offlinePlayers > 0
            ? 'critical'
            : (($degradedPlayers > 0 || $healthStatus === 'degraded') ? 'warning' : 'info');
        $urgency = $severity === 'critical'
            ? 'critical'
            : ($severity === 'warning' ? 'high' : 'normal');

        $title = match ($severity) {
            'critical' => 'Wall em risco',
            'warning' => 'Wall degradado',
            default => 'Wall saudavel',
        };

        $summaryText = $offlinePlayers > 0
            ? "{$offlinePlayers} player(s) offline no telao."
            : ($degradedPlayers > 0
                ? "{$degradedPlayers} player(s) degradado(s) no telao."
                : 'Players do wall seguem saudaveis.');

        return $this->mappedPayload(
            eventKey: 'wall.health.changed',
            severity: $severity,
            urgency: $urgency,
            payload: [
                'event_media_id' => null,
                'inbound_message_id' => null,
                'station_key' => 'wall',
                'title' => $title,
                'summary' => $summaryText,
                'payload_json' => $summary,
                'animation_hint' => $severity === 'critical' ? 'wall_alert' : ($severity === 'warning' ? 'wall_warning' : 'wall_idle'),
                'station_load' => $this->wallStationLoad($summary),
                'queue_depth' => max($offlinePlayers + $degradedPlayers, 0),
                'render_group' => 'wall',
                'dedupe_window_key' => $this->dedupeWindowKey('wall-health', $occurredAt, $healthStatus),
                'correlation_key' => "wall-health:{$event->eventId}",
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromWhatsAppFeedback(WhatsAppMessageFeedback $feedback): ?array
    {
        return $this->fromFeedbackModel(
            eventId: $feedback->event_id,
            feedbackStatus: (string) $feedback->status,
            feedbackKind: (string) $feedback->feedback_kind,
            feedbackPhase: (string) $feedback->feedback_phase,
            eventMediaId: $feedback->event_media_id,
            inboundMessageId: $feedback->inbound_message_id,
            correlationKey: $feedback->trace_id ?: "whatsapp-feedback:{$feedback->id}",
            occurredAt: $feedback->completed_at ?? $feedback->attempted_at ?? now(),
            payload: [
                'provider' => 'whatsapp',
                'feedback_kind' => $feedback->feedback_kind,
                'feedback_phase' => $feedback->feedback_phase,
                'status' => $feedback->status,
                'chat_external_id' => $feedback->chat_external_id,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromTelegramFeedback(TelegramMessageFeedback $feedback): ?array
    {
        return $this->fromFeedbackModel(
            eventId: $feedback->event_id,
            feedbackStatus: (string) $feedback->status,
            feedbackKind: (string) $feedback->feedback_kind,
            feedbackPhase: (string) $feedback->feedback_phase,
            eventMediaId: $feedback->event_media_id,
            inboundMessageId: $feedback->inbound_message_id,
            correlationKey: $feedback->trace_id ?: "telegram-feedback:{$feedback->id}",
            occurredAt: $feedback->completed_at ?? $feedback->attempted_at ?? now(),
            payload: [
                'provider' => 'telegram',
                'feedback_kind' => $feedback->feedback_kind,
                'feedback_phase' => $feedback->feedback_phase,
                'status' => $feedback->status,
                'chat_external_id' => $feedback->chat_external_id,
            ],
        );
    }

    private function moderationStationFor(EventMedia $media, bool $approved): string
    {
        $moderationMode = $media->event?->moderation_mode?->value;

        if (! $approved && in_array((string) $media->safety_status, ['block', 'failed'], true)) {
            return 'safety';
        }

        if ($moderationMode === 'manual' || $media->decision_overridden_by_user_id !== null) {
            return 'human_review';
        }

        return $approved ? 'human_review' : 'safety';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function mappedPayload(string $eventKey, string $severity, string $urgency, array $payload): array
    {
        $payload['event_key'] = $eventKey;
        $payload['severity'] = $severity;
        $payload['urgency'] = $urgency;
        $payload['priority'] = $this->priority->resolve($severity, $urgency, $eventKey);

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromFeedbackModel(
        ?int $eventId,
        string $feedbackStatus,
        string $feedbackKind,
        string $feedbackPhase,
        ?int $eventMediaId,
        ?int $inboundMessageId,
        string $correlationKey,
        CarbonInterface $occurredAt,
        array $payload,
    ): ?array {
        if (! $eventId || ! in_array($feedbackStatus, ['sent', 'failed'], true)) {
            return null;
        }

        $isFailed = $feedbackStatus === 'failed';

        return $this->mappedPayload(
            eventKey: $isFailed ? 'feedback.failed' : 'feedback.sent',
            severity: $isFailed ? 'warning' : 'info',
            urgency: $isFailed ? 'high' : 'low',
            payload: [
                'event_media_id' => $eventMediaId,
                'inbound_message_id' => $inboundMessageId,
                'station_key' => 'feedback',
                'title' => $isFailed ? 'Feedback falhou' : 'Feedback enviado',
                'summary' => $isFailed
                    ? "O feedback {$feedbackKind} {$feedbackPhase} falhou."
                    : "O feedback {$feedbackKind} {$feedbackPhase} foi enviado.",
                'payload_json' => $payload,
                'animation_hint' => $isFailed ? 'feedback_warning' : 'feedback_sent',
                'station_load' => $isFailed ? 0.68 : 0.18,
                'queue_depth' => $isFailed ? 1 : 0,
                'render_group' => 'publishing',
                'dedupe_window_key' => $this->dedupeWindowKey('feedback', $occurredAt, $payload['provider'] ?? 'provider'),
                'correlation_key' => $correlationKey,
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaPayload(EventMedia $media): array
    {
        return [
            'media_type' => $media->media_type,
            'source_type' => $media->source_type,
            'caption' => $media->caption,
            'original_filename' => $media->displayFilename(),
            'preview_url' => null,
        ];
    }

    private function dedupeWindowKey(string $prefix, CarbonInterface $occurredAt, string|int|null $suffix = null): string
    {
        $window = $occurredAt->format('YmdHi');
        $suffixText = trim((string) ($suffix ?? ''));

        return $suffixText !== ''
            ? "{$prefix}:{$window}:{$suffixText}"
            : "{$prefix}:{$window}";
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function wallStationLoad(array $summary): float
    {
        $offlinePlayers = (int) ($summary['offline_players'] ?? 0);
        $degradedPlayers = (int) ($summary['degraded_players'] ?? 0);
        $errorCount = (int) ($summary['error_count'] ?? 0);
        $staleCount = (int) ($summary['stale_count'] ?? 0);

        return min(
            1.0,
            round(($offlinePlayers * 0.6) + ($degradedPlayers * 0.25) + ($errorCount * 0.05) + ($staleCount * 0.05), 2),
        );
    }
}
