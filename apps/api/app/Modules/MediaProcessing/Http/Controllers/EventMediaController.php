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
use App\Modules\MediaProcessing\Http\Requests\UpdateEventMediaFeaturedRequest;
use App\Modules\MediaProcessing\Http\Requests\UpdateEventMediaPinnedRequest;
use App\Modules\MediaProcessing\Http\Resources\EventMediaDetailResource;
use App\Modules\MediaProcessing\Http\Resources\EventMediaPipelineMetricsResource;
use App\Modules\MediaProcessing\Http\Resources\EventMediaResource;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Queries\ListCatalogMediaQuery;
use App\Modules\MediaProcessing\Queries\ListModerationMediaQuery;
use App\Modules\MediaProcessing\Services\MediaAuditLogger;
use App\Modules\MediaProcessing\Services\MediaPipelineMetricsService;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\MediaProcessing\Services\EventMediaSenderContextService;
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
            featured: array_key_exists('featured', $validated) ? (bool) $validated['featured'] : null,
            pinned: array_key_exists('pinned', $validated) ? (bool) $validated['pinned'] : null,
            senderBlocked: array_key_exists('sender_blocked', $validated) ? (bool) $validated['sender_blocked'] : null,
            orientation: $validated['orientation'] ?? null,
        );

        $perPage = (int) ($validated['per_page'] ?? 24);
        $page = $query->fetchCursorPage(
            perPage: $perPage,
            cursor: $validated['cursor'] ?? null,
        );
        $includeStats = empty($validated['cursor']);

        app(EventMediaSenderContextService::class)->hydrateCollection($page['items']);

        return response()->json([
            'success' => true,
            'data' => EventMediaResource::collection($page['items'])->resolve(),
            'meta' => [
                'per_page' => $perPage,
                'next_cursor' => $page['next_cursor'],
                'prev_cursor' => null,
                'has_more' => $page['has_more'],
                'stats' => $includeStats
                    ? $this->cachedModerationStats($query, $organizationId, $validated)
                    : null,
                'request_id' => 'req_' . Str::random(12),
            ],
        ]);
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
        ]));

        return Cache::remember(
            "moderation:stats:org:{$organizationId}:{$cacheFingerprint}",
            now()->addSeconds(15),
            fn () => $query->stats(),
        );
    }
}
