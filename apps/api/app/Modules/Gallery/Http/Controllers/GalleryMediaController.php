<?php

namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\Gallery\Actions\HideGalleryMediaAction;
use App\Modules\Gallery\Actions\PublishGalleryMediaAction;
use App\Modules\Gallery\Http\Requests\ListGalleryMediaRequest;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Http\Resources\EventMediaResource;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Queries\ListCatalogMediaQuery;
use App\Modules\MediaProcessing\Services\EventMediaSenderContextService;
use App\Shared\Http\BaseController;
use App\Shared\Support\EventAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GalleryMediaController extends BaseController
{
    private const MEDIA_RELATIONS = ['event', 'variants', 'inboundMessage'];

    public function catalogIndex(ListGalleryMediaRequest $request): JsonResponse
    {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        $validated = $request->validated();

        $query = new ListCatalogMediaQuery(
            organizationId: $organizationId,
            eventId: $validated['event_id'] ?? null,
            search: $validated['search'] ?? null,
            status: ModerationStatus::Approved->value,
            channel: $validated['channel'] ?? null,
            mediaType: $validated['media_type'] ?? null,
            featured: array_key_exists('featured', $validated) ? (bool) $validated['featured'] : null,
            pinned: array_key_exists('pinned', $validated) ? (bool) $validated['pinned'] : null,
            publicationStatus: $validated['publication_status'] ?? null,
            orientation: $validated['orientation'] ?? null,
            createdFrom: $validated['created_from'] ?? null,
            createdTo: $validated['created_to'] ?? null,
            sortBy: $validated['sort_by'] ?? 'sort_order',
            sortDirection: $validated['sort_direction'] ?? 'desc',
        );

        $media = $query->query()->paginate($validated['per_page'] ?? 30);
        app(EventMediaSenderContextService::class)->hydrateCollection($media->getCollection());

        return response()->json([
            'success' => true,
            'data' => EventMediaResource::collection($media)->resolve(),
            'meta' => [
                'page' => $media->currentPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
                'last_page' => $media->lastPage(),
                'stats' => $this->catalogStats($query),
                'request_id' => 'req_' . Str::random(12),
            ],
        ]);
    }

    public function index(Request $request, int $event, EventAccessService $eventAccess): JsonResponse
    {
        $this->authorizeEventAccess($request, $event, 'gallery.view', $eventAccess);

        $media = EventMedia::where('event_id', $event)
            ->approved()
            ->with(self::MEDIA_RELATIONS)
            ->orderByDesc('sort_order')
            ->orderByDesc('published_at')
            ->paginate(30);
        app(EventMediaSenderContextService::class)->hydrateCollection($media->getCollection());

        return $this->paginated(EventMediaResource::collection($media));
    }

    public function feature(Request $request, int $event, int $media, EventAccessService $eventAccess): JsonResponse
    {
        $this->authorizeEventAccess($request, $event, 'gallery.manage', $eventAccess);

        $eventMedia = $this->findEventMedia($event, $media);
        $eventMedia->update(['is_featured' => !$eventMedia->is_featured]);

        return $this->success($this->resource($eventMedia));
    }

    public function publish(
        Request $request,
        int $event,
        int $media,
        EventAccessService $eventAccess,
        PublishGalleryMediaAction $action,
    ): JsonResponse
    {
        $this->authorizeEventAccess($request, $event, 'gallery.manage', $eventAccess);

        $eventMedia = $this->findEventMedia($event, $media);

        return $this->success($this->resource(
            $action->execute($eventMedia, $request->user())
        ));
    }

    public function remove(
        Request $request,
        int $event,
        int $media,
        EventAccessService $eventAccess,
        HideGalleryMediaAction $action,
    ): JsonResponse
    {
        $this->authorizeEventAccess($request, $event, 'gallery.manage', $eventAccess);

        $eventMedia = $this->findEventMedia($event, $media);

        return $this->success($this->resource(
            $action->execute($eventMedia, $request->user())
        ));
    }

    private function authorizeEventAccess(
        Request $request,
        int $eventId,
        string $permission,
        EventAccessService $eventAccess,
    ): void {
        abort_unless($eventAccess->can($request->user(), $eventId, $permission), 403);
    }

    private function findEventMedia(int $eventId, int $mediaId): EventMedia
    {
        return EventMedia::query()
            ->where('event_id', $eventId)
            ->findOrFail($mediaId);
    }

    private function resource(EventMedia $eventMedia): EventMediaResource
    {
        return new EventMediaResource(
            $eventMedia->fresh(self::MEDIA_RELATIONS)
        );
    }

    private function catalogStats(ListCatalogMediaQuery $query): array
    {
        $baseQuery = $query->query(withStatusFilter: true);

        return [
            'total' => (clone $baseQuery)->count(),
            'images' => (clone $baseQuery)->where('media_type', 'image')->count(),
            'videos' => (clone $baseQuery)->where('media_type', 'video')->count(),
            'pending' => (clone $baseQuery)->where('moderation_status', ModerationStatus::Pending)->count(),
            'published' => (clone $baseQuery)->where('publication_status', 'published')->count(),
            'featured' => (clone $baseQuery)->where('is_featured', true)->count(),
            'pinned' => (clone $baseQuery)->where('sort_order', '>', 0)->count(),
            'duplicates' => (clone $baseQuery)->whereNotNull('duplicate_group_key')->count(),
            'face_indexed' => (clone $baseQuery)->where('face_index_status', 'indexed')->count(),
        ];
    }
}
