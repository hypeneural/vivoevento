<?php
namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\MediaProcessing\Http\Resources\EventMediaResource;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Http\BaseController;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class PublicGalleryController extends BaseController
{
    public function index(Request $request, string $event, AnalyticsTracker $analytics): JsonResponse
    {
        $eventModel = \App\Modules\Events\Models\Event::with('modules')
            ->where('slug', $event)
            ->firstOrFail();

        if (! $eventModel->isModuleEnabled('live')) {
            return $this->error('Galeria publica indisponivel para este evento.', 404);
        }

        if (! $eventModel->isActive()) {
            return $this->error('Galeria publica indisponivel no momento.', 410);
        }

        $analytics->trackEvent(
            $eventModel,
            'gallery.page_view',
            $request,
            ['surface' => 'gallery'],
            channel: 'gallery',
        );

        $media = EventMedia::where('event_id', $eventModel->id)
            ->published()
            ->approved()
            ->with(['variants', 'inboundMessage'])
            ->orderByDesc('sort_order')
            ->orderByDesc('published_at')
            ->paginate(30);

        $collection = EventMediaResource::collection($media);

        return $this->publicGalleryPaginated($collection, [
            'public_search_enabled' => $eventModel->allowsPublicSelfieSearch(),
            'find_me_url' => $eventModel->allowsPublicSelfieSearch() ? $eventModel->publicFindMeUrl() : null,
        ]);
    }

    protected function publicGalleryPaginated(AnonymousResourceCollection $collection, array $faceSearchMeta): JsonResponse
    {
        $paginator = $collection->resource;

        return response()->json([
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
}
