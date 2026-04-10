<?php

namespace App\Modules\MediaProcessing\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Actions\ApproveEventMediaAction;
use App\Modules\MediaProcessing\Actions\BulkApproveEventMediaAction;
use App\Modules\MediaProcessing\Actions\BulkRejectEventMediaAction;
use App\Modules\MediaProcessing\Actions\BulkUpdateEventMediaFeaturedAction;
use App\Modules\MediaProcessing\Actions\BulkUpdateEventMediaPinnedAction;
use App\Modules\MediaProcessing\Actions\DeactivateEventMediaSenderBlockAction;
use App\Modules\MediaProcessing\Actions\DeleteEventMediaAction;
use App\Modules\MediaProcessing\Actions\RejectEventMediaAction;
use App\Modules\MediaProcessing\Actions\ReprocessEventMediaStageAction;
use App\Modules\MediaProcessing\Actions\UndoEventMediaDecisionAction;
use App\Modules\MediaProcessing\Actions\UpsertEventMediaSenderBlockAction;
use App\Modules\MediaProcessing\Enums\MediaReprocessStage;
use App\Modules\MediaProcessing\Actions\UpdateEventMediaFeaturedAction;
use App\Modules\MediaProcessing\Actions\UpdateEventMediaPinnedAction;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Http\Requests\BulkModerationIdsRequest;
use App\Modules\MediaProcessing\Http\Requests\BulkUpdateEventMediaFeaturedRequest;
use App\Modules\MediaProcessing\Http\Requests\BulkUpdateEventMediaPinnedRequest;
use App\Modules\MediaProcessing\Http\Requests\ListCatalogMediaRequest;
use App\Modules\MediaProcessing\Http\Requests\ListEventMediaRequest;
use App\Modules\MediaProcessing\Http\Requests\ListModerationMediaRequest;
use App\Modules\MediaProcessing\Http\Requests\ReprocessEventMediaStageRequest;
use App\Modules\MediaProcessing\Http\Requests\ShowEventMediaPipelineMetricsRequest;
use App\Modules\MediaProcessing\Http\Requests\UpsertEventMediaSenderBlockRequest;
use App\Modules\MediaProcessing\Http\Resources\EventMediaAiDebugResource;
use App\Modules\MediaProcessing\Http\Requests\UpdateEventMediaFeaturedRequest;
use App\Modules\MediaProcessing\Http\Requests\UpdateEventMediaPinnedRequest;
use App\Modules\MediaProcessing\Http\Resources\EventMediaDetailResource;
use App\Modules\MediaProcessing\Http\Resources\EventMediaPipelineMetricsResource;
use App\Modules\MediaProcessing\Http\Resources\EventMediaResource;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Queries\ListCatalogMediaQuery;
use App\Modules\MediaProcessing\Queries\ListDuplicateClusterMediaQuery;
use App\Modules\MediaProcessing\Queries\ListModerationMediaQuery;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\MediaProcessing\Services\MediaPipelineMetricsService;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\MediaProcessing\Services\EventMediaSenderContextService;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\Telegram\Models\TelegramMessageFeedback;
use App\Modules\WhatsApp\Models\WhatsAppDispatchLog;
use App\Modules\WhatsApp\Models\WhatsAppInboundEvent;
use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EventMediaController extends BaseController
{
    public function catalogIndex(ListCatalogMediaRequest $request): JsonResponse
    {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        $validated = $request->validated();

        $query = new ListCatalogMediaQuery(
            organizationId: $organizationId,
            eventId: $validated['event_id'] ?? null,
            search: $validated['search'] ?? null,
            status: $validated['status'] ?? null,
            channel: $validated['channel'] ?? null,
            mediaType: $validated['media_type'] ?? null,
            featured: array_key_exists('featured', $validated) ? (bool) $validated['featured'] : null,
            pinned: array_key_exists('pinned', $validated) ? (bool) $validated['pinned'] : null,
            duplicates: array_key_exists('duplicates', $validated) ? (bool) $validated['duplicates'] : null,
            hasCaption: array_key_exists('has_caption', $validated) ? (bool) $validated['has_caption'] : null,
            faceSearchEnabled: array_key_exists('face_search_enabled', $validated) ? (bool) $validated['face_search_enabled'] : null,
            faceIndexStatus: $validated['face_index_status'] ?? null,
            safetyStatus: $validated['safety_status'] ?? null,
            vlmStatus: $validated['vlm_status'] ?? null,
            decisionSource: $validated['decision_source'] ?? null,
            publicationStatus: $validated['publication_status'] ?? null,
            orientation: $validated['orientation'] ?? null,
            createdFrom: $validated['created_from'] ?? null,
            createdTo: $validated['created_to'] ?? null,
            sortBy: $validated['sort_by'] ?? 'created_at',
            sortDirection: $validated['sort_direction'] ?? 'desc',
        );

        $media = $query->query()->paginate($validated['per_page'] ?? 30);

        return response()->json([
            'success' => true,
            'data' => EventMediaResource::collection($media)->resolve(),
            'meta' => [
                'page' => $media->currentPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
                'last_page' => $media->lastPage(),
                'stats' => $this->cachedCatalogStats($query, $organizationId, $validated),
                'request_id' => 'req_' . Str::random(12),
            ],
        ]);
    }

    public function moderationFeed(ListModerationMediaRequest $request): JsonResponse
    {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        $validated = $request->validated();

        $query = new ListModerationMediaQuery(
            organizationId: $organizationId,
            eventId: $validated['event_id'] ?? null,
            search: $validated['search'] ?? null,
            status: $validated['status'] ?? null,
            mediaType: $validated['media_type'] ?? null,
            featured: array_key_exists('featured', $validated) ? (bool) $validated['featured'] : null,
            pinned: array_key_exists('pinned', $validated) ? (bool) $validated['pinned'] : null,
            senderBlocked: array_key_exists('sender_blocked', $validated) ? (bool) $validated['sender_blocked'] : null,
            orientation: $validated['orientation'] ?? null,
            duplicates: array_key_exists('duplicates', $validated) ? (bool) $validated['duplicates'] : null,
            aiReview: array_key_exists('ai_review', $validated) ? (bool) $validated['ai_review'] : null,
        );

        $perPage = (int) ($validated['per_page'] ?? 24);
        $page = $query->fetchCursorPage(
            perPage: $perPage,
            cursor: $validated['cursor'] ?? null,
        );

        app(EventMediaSenderContextService::class)->hydrateCollection($page['items']);

        return response()->json([
            'success' => true,
            'data' => EventMediaResource::collection($page['items'])->resolve(),
            'meta' => [
                'per_page' => $perPage,
                'next_cursor' => $page['next_cursor'],
                'prev_cursor' => null,
                'has_more' => $page['has_more'],
                'stats' => null,
                'request_id' => 'req_' . Str::random(12),
            ],
        ]);
    }

    public function moderationFeedStats(ListModerationMediaRequest $request): JsonResponse
    {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        $validated = $request->validated();

        $query = new ListModerationMediaQuery(
            organizationId: $organizationId,
            eventId: $validated['event_id'] ?? null,
            search: $validated['search'] ?? null,
            status: $validated['status'] ?? null,
            mediaType: $validated['media_type'] ?? null,
            featured: array_key_exists('featured', $validated) ? (bool) $validated['featured'] : null,
            pinned: array_key_exists('pinned', $validated) ? (bool) $validated['pinned'] : null,
            senderBlocked: array_key_exists('sender_blocked', $validated) ? (bool) $validated['sender_blocked'] : null,
            orientation: $validated['orientation'] ?? null,
            duplicates: array_key_exists('duplicates', $validated) ? (bool) $validated['duplicates'] : null,
            aiReview: array_key_exists('ai_review', $validated) ? (bool) $validated['ai_review'] : null,
        );

        return $this->success(
            $this->cachedModerationStats($query, $organizationId, $validated)
        );
    }

    public function index(
        ListEventMediaRequest $request,
        Event $event,
        EventAccessService $eventAccess,
    ): JsonResponse
    {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        $query = EventMedia::query()
            ->where('event_id', $event->id)
            ->with(['variants', 'inboundMessage'])
            ->latest()
            ->when($request->validated('status'), function (Builder $builder, string $status) {
                match ($status) {
                    'published' => $builder->where('publication_status', PublicationStatus::Published),
                    'approved' => $builder->where('moderation_status', ModerationStatus::Approved),
                    'rejected' => $builder->where('moderation_status', ModerationStatus::Rejected),
                    'pending_moderation' => $builder->where('moderation_status', ModerationStatus::Pending),
                    'processing' => $builder->whereIn('processing_status', ['downloaded', 'processed']),
                    'error' => $builder->where('processing_status', 'failed'),
                    default => $builder->where('processing_status', 'received'),
                };
            });

        $media = $query->paginate($request->validated('per_page', 30));

        return $this->paginated(EventMediaResource::collection($media));
    }

    public function show(
        Request $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
    ): JsonResponse
    {
        $eventMedia->loadMissing('event');

        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.view'), 403);

        $eventMedia = $eventMedia->load([
            'event.faceSearchSettings',
            'variants',
            'processingRuns',
            'inboundMessage',
            'decisionOverriddenBy:id,name,email',
            'latestSafetyEvaluation',
            'latestVlmEvaluation',
        ])->loadCount('faces');

        app(EventMediaSenderContextService::class)->hydrateModel($eventMedia, includeMediaCount: true);

        return $this->success(new EventMediaDetailResource(
            $eventMedia
        ));
    }

    public function duplicateCluster(
        Request $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        EventMediaSenderContextService $senderContext,
    ): JsonResponse
    {
        $eventMedia->loadMissing('event');

        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.view'), 403);

        $cluster = (new ListDuplicateClusterMediaQuery($eventMedia))->get();
        $senderContext->hydrateCollection($cluster);

        return $this->success(EventMediaResource::collection($cluster)->resolve());
    }

    public function aiDebug(
        Request $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
    ): JsonResponse {
        $eventMedia->loadMissing([
            'event',
            'inboundMessage',
            'latestSafetyEvaluation',
            'latestVlmEvaluation',
        ]);

        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.view'), 403);

        $inboundMessage = $eventMedia->inboundMessage;
        $traceId = $inboundMessage?->trace_id
            ?? data_get($inboundMessage?->normalized_payload_json, '_event_context.trace_id');
        $providerMessageId = data_get($inboundMessage?->normalized_payload_json, '_event_context.provider_message_id')
            ?? $inboundMessage?->message_id;

        $webhookLogs = $inboundMessage
            ? ChannelWebhookLog::query()
                ->where('inbound_message_id', $inboundMessage->id)
                ->orderBy('id')
                ->get()
            : collect();

        $whatsAppEventsQuery = WhatsAppInboundEvent::query();

        if ($providerMessageId) {
            $whatsAppEventsQuery->where('provider_message_id', $providerMessageId);
        } elseif ($traceId) {
            $whatsAppEventsQuery->where('trace_id', $traceId);
        } else {
            $whatsAppEventsQuery->whereRaw('1 = 0');
        }

        $whatsAppEvents = $whatsAppEventsQuery
            ->orderByDesc('id')
            ->get();

        $whatsAppFeedbacks = WhatsAppMessageFeedback::query()
            ->with('outboundMessage')
            ->where('event_media_id', $eventMedia->id)
            ->orderBy('id')
            ->get();

        $telegramFeedbacks = TelegramMessageFeedback::query()
            ->where('event_media_id', $eventMedia->id)
            ->orderBy('id')
            ->get();

        $whatsAppDispatchLogs = WhatsAppDispatchLog::query()
            ->whereIn('message_id', $whatsAppFeedbacks->pluck('outbound_message_id')->filter()->all())
            ->orderBy('id')
            ->get();

        return $this->success(new EventMediaAiDebugResource([
            'media_id' => $eventMedia->id,
            'event_id' => $eventMedia->event_id,
            'trace_id' => $traceId,
            'inbound_message' => $inboundMessage ? [
                'id' => $inboundMessage->id,
                'trace_id' => $inboundMessage->trace_id,
                'provider' => $inboundMessage->provider,
                'message_id' => $inboundMessage->message_id,
                'message_type' => $inboundMessage->message_type,
                'chat_external_id' => $inboundMessage->chat_external_id,
                'sender_external_id' => $inboundMessage->sender_external_id,
                'sender_phone' => $inboundMessage->sender_phone,
                'sender_name' => $inboundMessage->sender_name,
                'status' => $inboundMessage->status,
                'normalized_payload' => $inboundMessage->normalized_payload_json ?? [],
                'received_at' => $inboundMessage->received_at?->toIso8601String(),
                'processed_at' => $inboundMessage->processed_at?->toIso8601String(),
            ] : null,
            'webhook_logs' => $webhookLogs->map(fn (ChannelWebhookLog $log) => [
                'id' => $log->id,
                'trace_id' => $log->trace_id,
                'provider' => $log->provider,
                'provider_update_id' => $log->provider_update_id,
                'message_id' => $log->message_id,
                'detected_type' => $log->detected_type,
                'routing_status' => $log->routing_status,
                'error_message' => $log->error_message,
                'payload' => $log->payload_json ?? [],
                'created_at' => $log->created_at?->toIso8601String(),
                'updated_at' => $log->updated_at?->toIso8601String(),
            ])->values()->all(),
            'whatsapp_events' => $whatsAppEvents->map(fn (WhatsAppInboundEvent $event) => [
                'id' => $event->id,
                'trace_id' => $event->trace_id,
                'provider_key' => $event->provider_key?->value ?? $event->provider_key,
                'provider_message_id' => $event->provider_message_id,
                'event_type' => $event->event_type,
                'processing_status' => $event->processing_status?->value ?? $event->processing_status,
                'payload' => $event->payload_json ?? [],
                'normalized' => $event->normalized_json ?? [],
                'error_message' => $event->error_message,
                'received_at' => $event->received_at?->toIso8601String(),
                'processed_at' => $event->processed_at?->toIso8601String(),
            ])->values()->all(),
            'safety' => $eventMedia->latestSafetyEvaluation ? [
                'id' => $eventMedia->latestSafetyEvaluation->id,
                'provider_key' => $eventMedia->latestSafetyEvaluation->provider_key,
                'model_key' => $eventMedia->latestSafetyEvaluation->model_key,
                'decision' => $eventMedia->latestSafetyEvaluation->decision,
                'blocked' => (bool) $eventMedia->latestSafetyEvaluation->blocked,
                'review_required' => (bool) $eventMedia->latestSafetyEvaluation->review_required,
                'request_payload' => $eventMedia->latestSafetyEvaluation->request_payload_json ?? [],
                'raw_response' => $eventMedia->latestSafetyEvaluation->raw_response_json ?? [],
                'normalized_provider' => $eventMedia->latestSafetyEvaluation->normalized_provider_json ?? [],
                'reason_codes' => $eventMedia->latestSafetyEvaluation->reason_codes_json ?? [],
                'completed_at' => $eventMedia->latestSafetyEvaluation->completed_at?->toIso8601String(),
            ] : null,
            'vlm' => $eventMedia->latestVlmEvaluation ? [
                'id' => $eventMedia->latestVlmEvaluation->id,
                'provider_key' => $eventMedia->latestVlmEvaluation->provider_key,
                'model_key' => $eventMedia->latestVlmEvaluation->model_key,
                'decision' => $eventMedia->latestVlmEvaluation->decision,
                'review_required' => (bool) $eventMedia->latestVlmEvaluation->review_required,
                'short_caption' => $eventMedia->latestVlmEvaluation->short_caption,
                'reply_text' => $eventMedia->latestVlmEvaluation->reply_text,
                'request_payload' => $eventMedia->latestVlmEvaluation->request_payload_json ?? [],
                'raw_response' => $eventMedia->latestVlmEvaluation->raw_response_json ?? [],
                'prompt_context' => $eventMedia->latestVlmEvaluation->prompt_context_json ?? [],
                'completed_at' => $eventMedia->latestVlmEvaluation->completed_at?->toIso8601String(),
            ] : null,
            'whatsapp_feedbacks' => $whatsAppFeedbacks->map(fn (WhatsAppMessageFeedback $feedback) => [
                'id' => $feedback->id,
                'trace_id' => $feedback->trace_id,
                'feedback_kind' => $feedback->feedback_kind,
                'feedback_phase' => $feedback->feedback_phase,
                'status' => $feedback->status,
                'reaction_emoji' => $feedback->reaction_emoji,
                'reply_text' => $feedback->reply_text,
                'resolution' => $feedback->resolution_json ?? [],
                'outbound_message_id' => $feedback->outbound_message_id,
                'error_message' => $feedback->error_message,
                'attempted_at' => $feedback->attempted_at?->toIso8601String(),
                'completed_at' => $feedback->completed_at?->toIso8601String(),
            ])->values()->all(),
            'telegram_feedbacks' => $telegramFeedbacks->map(fn (TelegramMessageFeedback $feedback) => [
                'id' => $feedback->id,
                'trace_id' => $feedback->trace_id,
                'feedback_kind' => $feedback->feedback_kind,
                'feedback_phase' => $feedback->feedback_phase,
                'status' => $feedback->status,
                'reaction_emoji' => $feedback->reaction_emoji,
                'chat_action' => $feedback->chat_action,
                'reply_text' => $feedback->reply_text,
                'resolution' => $feedback->resolution_json ?? [],
                'error_message' => $feedback->error_message,
                'attempted_at' => $feedback->attempted_at?->toIso8601String(),
                'completed_at' => $feedback->completed_at?->toIso8601String(),
            ])->values()->all(),
            'whatsapp_dispatch_logs' => $whatsAppDispatchLogs->map(fn (WhatsAppDispatchLog $log) => [
                'id' => $log->id,
                'message_id' => $log->message_id,
                'provider_key' => $log->provider_key?->value ?? $log->provider_key,
                'endpoint_used' => $log->endpoint_used,
                'request' => $log->request_json ?? [],
                'response' => $log->response_json ?? [],
                'http_status' => $log->http_status,
                'success' => (bool) $log->success,
                'error_message' => $log->error_message,
                'duration_ms' => $log->duration_ms,
                'created_at' => method_exists($log->created_at, 'toIso8601String')
                    ? $log->created_at->toIso8601String()
                    : $log->created_at,
            ])->values()->all(),
        ]));
    }

    public function approve(
        Request $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        ApproveEventMediaAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.moderate'), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $eventMedia = $action->execute(
            $eventMedia,
            $request->user(),
            $validated['reason'] ?? null,
        );
        $broadcaster->broadcastUpdated($eventMedia);

        return $this->success(new EventMediaResource($eventMedia));
    }

    public function reject(
        Request $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        RejectEventMediaAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.moderate'), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $eventMedia = $action->execute(
            $eventMedia,
            $request->user(),
            $validated['reason'] ?? null,
        );
        $broadcaster->broadcastUpdated($eventMedia);

        return $this->success(new EventMediaResource($eventMedia));
    }

    public function undoDecision(
        Request $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        UndoEventMediaDecisionAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.moderate'), 403);

        $eventMedia = $action->execute(
            $eventMedia,
            $request->user(),
        );
        $broadcaster->broadcastUpdated($eventMedia);

        return $this->success(new EventMediaResource($eventMedia));
    }

    public function updateFeatured(
        UpdateEventMediaFeaturedRequest $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        UpdateEventMediaFeaturedAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.moderate'), 403);

        $eventMedia = $action->execute(
            $eventMedia,
            (bool) $request->validated('is_featured'),
        );

        $broadcaster->broadcastUpdated($eventMedia);

        return $this->success(new EventMediaResource($eventMedia));
    }

    public function updatePinned(
        UpdateEventMediaPinnedRequest $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        UpdateEventMediaPinnedAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.moderate'), 403);

        $eventMedia = $action->execute(
            $eventMedia,
            (bool) $request->validated('is_pinned'),
        );

        $broadcaster->broadcastUpdated($eventMedia);

        return $this->success(new EventMediaResource($eventMedia));
    }

    public function blockSender(
        UpsertEventMediaSenderBlockRequest $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        UpsertEventMediaSenderBlockAction $action,
        EventMediaSenderContextService $senderContext,
    ): JsonResponse {
        $eventMedia->loadMissing('event');

        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.moderate'), 403);

        $action->execute(
            $eventMedia,
            reason: $request->validated('reason'),
            expiresAt: $request->validated('expires_at'),
        );

        $eventMedia = $eventMedia->fresh(['event', 'variants', 'inboundMessage']);
        $senderContext->hydrateModel($eventMedia, includeMediaCount: true);

        return $this->success(new EventMediaResource($eventMedia));
    }

    public function unblockSender(
        Request $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        DeactivateEventMediaSenderBlockAction $action,
        EventMediaSenderContextService $senderContext,
    ): JsonResponse {
        $eventMedia->loadMissing('event');

        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.moderate'), 403);

        $action->execute($eventMedia);

        $eventMedia = $eventMedia->fresh(['event', 'variants', 'inboundMessage']);
        $senderContext->hydrateModel($eventMedia, includeMediaCount: true);

        return $this->success(new EventMediaResource($eventMedia));
    }

    public function bulkApprove(
        BulkModerationIdsRequest $request,
        BulkApproveEventMediaAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        $mediaItems = $this->resolveBulkMedia($request, $request->validated('ids'));
        $updated = $action->execute(
            $mediaItems,
            $request->user(),
            $request->validated('reason'),
        );

        $updated->each(fn (EventMedia $media) => $broadcaster->broadcastUpdated($media));

        return $this->bulkSuccessResponse($updated);
    }

    public function bulkReject(
        BulkModerationIdsRequest $request,
        BulkRejectEventMediaAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        $mediaItems = $this->resolveBulkMedia($request, $request->validated('ids'));
        $updated = $action->execute(
            $mediaItems,
            $request->user(),
            $request->validated('reason'),
        );

        $updated->each(fn (EventMedia $media) => $broadcaster->broadcastUpdated($media));

        return $this->bulkSuccessResponse($updated);
    }

    public function bulkUpdateFeatured(
        BulkUpdateEventMediaFeaturedRequest $request,
        BulkUpdateEventMediaFeaturedAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        $mediaItems = $this->resolveBulkMedia($request, $request->validated('ids'));
        $updated = $action->execute(
            $mediaItems,
            (bool) $request->validated('is_featured'),
        );

        $updated->each(fn (EventMedia $media) => $broadcaster->broadcastUpdated($media));

        return $this->bulkSuccessResponse($updated);
    }

    public function bulkUpdatePinned(
        BulkUpdateEventMediaPinnedRequest $request,
        BulkUpdateEventMediaPinnedAction $action,
        ModerationBroadcasterService $broadcaster,
    ): JsonResponse
    {
        $mediaItems = $this->resolveBulkMedia($request, $request->validated('ids'));
        $updated = $action->execute(
            $mediaItems,
            (bool) $request->validated('is_pinned'),
        );

        $updated->each(fn (EventMedia $media) => $broadcaster->broadcastUpdated($media));

        return $this->bulkSuccessResponse($updated);
    }

    public function destroy(
        Request $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        MediaAuditLogger $auditLogger,
        ModerationBroadcasterService $broadcaster,
        DeleteEventMediaAction $deleteEventMedia,
    ): JsonResponse
    {
        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.delete'), 403);

        $actor = $request->user();
        $broadcaster->broadcastDeleted($eventMedia);

        if ($actor instanceof \App\Modules\Users\Models\User) {
            $auditLogger->log(
                actor: $actor,
                eventMedia: $eventMedia,
                event: 'media.deleted',
                description: 'Midia removida',
            );
        }

        $deleteEventMedia->execute($eventMedia);

        return $this->noContent();
    }

    public function reprocess(
        ReprocessEventMediaStageRequest $request,
        EventMedia $eventMedia,
        EventAccessService $eventAccess,
        ReprocessEventMediaStageAction $action,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $eventMedia->event, 'media.reprocess'), 403);

        $stage = MediaReprocessStage::from((string) $request->validated('stage'));

        $media = $action->execute(
            eventMedia: $eventMedia,
            stage: $stage,
            actor: $request->user(),
            reason: $request->validated('reason'),
        );

        return $this->success(new EventMediaResource(
            $media->loadMissing(['event', 'variants', 'inboundMessage'])
        ));
    }

    public function pipelineMetrics(
        ShowEventMediaPipelineMetricsRequest $request,
        Event $event,
        EventAccessService $eventAccess,
        MediaPipelineMetricsService $metrics,
    ): JsonResponse {
        abort_unless($eventAccess->can($request->user(), $event, 'media.view'), 403);

        $payload = $metrics->forEvent(
            event: $event,
            includeDeleted: (bool) $request->boolean('include_deleted', true),
        );

        return $this->success(new EventMediaPipelineMetricsResource($payload));
    }

    private function resolveBulkMedia(Request $request, array $ids): Collection
    {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        $orderedIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->values();

        $mediaItems = EventMedia::query()
            ->whereIn('id', $orderedIds)
            ->whereHas('event', fn (Builder $builder) => $builder->where('organization_id', $organizationId))
            ->with(['event', 'variants', 'inboundMessage'])
            ->get()
            ->sortBy(fn (EventMedia $media) => $orderedIds->search($media->id))
            ->values();

        abort_unless($mediaItems->count() === $orderedIds->count(), 403, 'Uma ou mais midias nao pertencem a organizacao ativa.');

        return $mediaItems;
    }

    private function bulkSuccessResponse(Collection $mediaItems): JsonResponse
    {
        return $this->success([
            'count' => $mediaItems->count(),
            'ids' => $mediaItems->pluck('id')->values(),
            'items' => EventMediaResource::collection($mediaItems)->resolve(),
        ]);
    }

    private function cachedCatalogStats(ListCatalogMediaQuery $query, int $organizationId, array $filters): array
    {
        $cacheFingerprint = md5((string) json_encode([
            'organization_id' => $organizationId,
            'event_id' => $filters['event_id'] ?? null,
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'channel' => $filters['channel'] ?? null,
            'media_type' => $filters['media_type'] ?? null,
            'featured' => array_key_exists('featured', $filters) ? (bool) $filters['featured'] : null,
            'pinned' => array_key_exists('pinned', $filters) ? (bool) $filters['pinned'] : null,
            'duplicates' => array_key_exists('duplicates', $filters) ? (bool) $filters['duplicates'] : null,
            'has_caption' => array_key_exists('has_caption', $filters) ? (bool) $filters['has_caption'] : null,
            'face_search_enabled' => array_key_exists('face_search_enabled', $filters) ? (bool) $filters['face_search_enabled'] : null,
            'face_index_status' => $filters['face_index_status'] ?? null,
            'safety_status' => $filters['safety_status'] ?? null,
            'vlm_status' => $filters['vlm_status'] ?? null,
            'decision_source' => $filters['decision_source'] ?? null,
            'publication_status' => $filters['publication_status'] ?? null,
            'orientation' => $filters['orientation'] ?? null,
            'created_from' => $filters['created_from'] ?? null,
            'created_to' => $filters['created_to'] ?? null,
            'sort_by' => $filters['sort_by'] ?? null,
            'sort_direction' => $filters['sort_direction'] ?? null,
        ]));

        return Cache::remember(
            "media:catalog:stats:org:{$organizationId}:{$cacheFingerprint}",
            now()->addSeconds(15),
            fn () => $query->stats(),
        );
    }

    private function cachedModerationStats(ListModerationMediaQuery $query, int $organizationId, array $filters): array
    {
        $cacheFingerprint = md5((string) json_encode([
            'organization_id' => $organizationId,
            'event_id' => $filters['event_id'] ?? null,
            'search' => $filters['search'] ?? null,
            'status' => $filters['status'] ?? null,
            'featured' => array_key_exists('featured', $filters) ? (bool) $filters['featured'] : null,
            'pinned' => array_key_exists('pinned', $filters) ? (bool) $filters['pinned'] : null,
            'sender_blocked' => array_key_exists('sender_blocked', $filters) ? (bool) $filters['sender_blocked'] : null,
            'orientation' => $filters['orientation'] ?? null,
            'media_type' => $filters['media_type'] ?? null,
            'duplicates' => array_key_exists('duplicates', $filters) ? (bool) $filters['duplicates'] : null,
            'ai_review' => array_key_exists('ai_review', $filters) ? (bool) $filters['ai_review'] : null,
        ]));

        return Cache::remember(
            "moderation:stats:org:{$organizationId}:{$cacheFingerprint}",
            now()->addSeconds(15),
            fn () => $query->stats(),
        );
    }
}
