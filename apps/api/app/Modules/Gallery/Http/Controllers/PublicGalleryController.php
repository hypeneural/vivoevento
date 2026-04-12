<?php
namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Gallery\Actions\BuildPublicGalleryPayloadAction;
use App\Modules\Gallery\Http\Resources\PublicGalleryMediaResource;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Http\BaseController;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class PublicGalleryController extends BaseController
{
    public function index(
        Request $request,
        string $event,
        AnalyticsTracker $analytics,
        BuildPublicGalleryPayloadAction $payloadBuilder,
    ): JsonResponse
    {
        $eventModel = $this->findAvailableEvent($event);

        if ($eventModel instanceof JsonResponse) {
            return $eventModel;
        }

        $this->trackPageView($analytics, $eventModel, $request);

        $collection = PublicGalleryMediaResource::collection($this->paginatedMedia($eventModel->id));
        $settings = EventGallerySetting::query()
            ->with('currentPublishedRevision')
            ->where('event_id', $eventModel->id)
            ->first();
        $payload = $payloadBuilder->execute(
            $eventModel,
            $settings?->currentPublishedRevision?->toBuilderPayload(),
            $settings?->published_version,
        );

        return $this->publicGalleryPaginated($collection, [
            'public_search_enabled' => $eventModel->allowsPublicSelfieSearch(),
            'find_me_url' => $eventModel->allowsPublicSelfieSearch() ? $eventModel->publicFindMeUrl() : null,
        ], $payload);
    }

    public function media(Request $request, string $event): JsonResponse
    {
        $eventModel = $this->findAvailableEvent($event);

        if ($eventModel instanceof JsonResponse) {
            return $eventModel;
        }

        return $this->publicGalleryPaginated(
            PublicGalleryMediaResource::collection($this->paginatedMedia($eventModel->id)),
            [
                'public_search_enabled' => $eventModel->allowsPublicSelfieSearch(),
                'find_me_url' => $eventModel->allowsPublicSelfieSearch() ? $eventModel->publicFindMeUrl() : null,
            ],
        );
    }

    public function preview(string $token, BuildPublicGalleryPayloadAction $payloadBuilder): JsonResponse
    {
        $settings = EventGallerySetting::query()
            ->with(['event.organization', 'event.modules', 'previewRevision'])
            ->where('preview_share_token', $token)
            ->whereNotNull('preview_revision_id')
            ->where(function ($query) {
                $query->whereNull('preview_share_expires_at')
                    ->orWhere('preview_share_expires_at', '>', now());
            })
            ->firstOrFail();

        abort_unless($settings->event, 404);
        abort_unless($settings->previewRevision, 404);

        $payload = $payloadBuilder->execute(
            $settings->event,
            $settings->previewRevision->toBuilderPayload(),
            $settings->published_version,
        );

        return $this->publicGalleryPaginated(
            PublicGalleryMediaResource::collection($this->paginatedMedia($settings->event_id)),
            [
                'public_search_enabled' => $settings->event->allowsPublicSelfieSearch(),
                'find_me_url' => $settings->event->allowsPublicSelfieSearch() ? $settings->event->publicFindMeUrl() : null,
            ],
            $payload,
        );
    }

    protected function publicGalleryPaginated(
        AnonymousResourceCollection $collection,
        array $faceSearchMeta,
        array $payload = [],
    ): JsonResponse
    {
        $paginator = $collection->resource;

        return response()->json($payload + [
            'success' => true,
            'data' => $collection->resolve(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'request_id' => (string) Context::remember('request_id', fn () => 'req_' . Str::random(12)),
                'face_search' => $faceSearchMeta,
            ],
        ]);
    }

    private function findAvailableEvent(string $event): \App\Modules\Events\Models\Event|JsonResponse
    {
        $eventModel = \App\Modules\Events\Models\Event::with(['modules', 'organization'])
            ->where('slug', $event)
            ->firstOrFail();

        if (! $eventModel->isModuleEnabled('live')) {
            return $this->error('Galeria publica indisponivel para este evento.', 404);
        }

        if (! $eventModel->isActive()) {
            return $this->error('Galeria publica indisponivel no momento.', 410);
        }

        return $eventModel;
    }

    private function paginatedMedia(int $eventId): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return EventMedia::where('event_id', $eventId)
            ->published()
            ->approved()
            ->with(['variants', 'inboundMessage'])
            ->orderByDesc('sort_order')
            ->orderByDesc('published_at')
            ->paginate(30);
    }

    private function trackPageView(
        AnalyticsTracker $analytics,
        \App\Modules\Events\Models\Event $event,
        Request $request,
    ): void {
        $analytics->trackEvent(
            $event,
            'gallery.page_view',
            $request,
            ['surface' => 'gallery'],
            channel: 'gallery',
        );
    }
}
