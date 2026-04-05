<?php
namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\MediaProcessing\Http\Resources\EventMediaResource;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->paginated(EventMediaResource::collection($media));
    }
}
