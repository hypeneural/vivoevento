<?php
namespace App\Modules\Gallery\Http\Controllers;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PublicGalleryController extends BaseController
{
    public function index(string $event): JsonResponse
    {
        $eventModel = \App\Modules\Events\Models\Event::where('slug', $event)->firstOrFail();

        $media = EventMedia::where('event_id', $eventModel->id)
            ->published()
            ->approved()
            ->with('variants')
            ->orderByDesc('published_at')
            ->paginate(30);

        return $this->success($media);
    }
}
