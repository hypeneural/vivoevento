<?php
namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class GalleryMediaController extends BaseController
{
    public function index(int $event): JsonResponse
    {
        $media = EventMedia::where('event_id', $event)
            ->approved()
            ->with('variants')
            ->orderBy('sort_order')
            ->paginate(30);

        return $this->success($media);
    }

    public function feature(int $event, int $media): JsonResponse
    {
        $eventMedia = EventMedia::where('event_id', $event)->findOrFail($media);
        $eventMedia->update(['is_featured' => !$eventMedia->is_featured]);

        return $this->success($eventMedia->fresh(), 'Feature status toggled');
    }

    public function remove(int $event, int $media): JsonResponse
    {
        $eventMedia = EventMedia::where('event_id', $event)->findOrFail($media);
        $eventMedia->update(['publication_status' => 'hidden']);

        return $this->success(message: 'Removed from gallery');
    }
}
