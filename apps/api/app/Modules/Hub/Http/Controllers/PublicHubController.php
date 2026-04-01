<?php

namespace App\Modules\Hub\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Hub\Models\EventHubSetting;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PublicHubController extends BaseController
{
    public function index(string $event): JsonResponse
    {
        $eventModel = Event::with(['modules', 'wallSettings'])
            ->where('slug', $event)
            ->firstOrFail();

        $settings = EventHubSetting::firstOrCreate(['event_id' => $eventModel->id]);

        $liveEnabled = $eventModel->isActive() && $eventModel->isModuleEnabled('live');
        $wallEnabled = $eventModel->isActive()
            && $eventModel->isModuleEnabled('wall')
            && $eventModel->wallSettings?->isAvailable();

        return $this->success([
            'event' => [
                'id' => $eventModel->id,
                'title' => $eventModel->title,
                'slug' => $eventModel->slug,
                'cover_image_path' => $eventModel->cover_image_path,
            ],
            'settings' => $settings,
            'modules' => [
                'live' => $eventModel->isModuleEnabled('live'),
                'wall' => $eventModel->isModuleEnabled('wall'),
                'play' => $eventModel->isModuleEnabled('play'),
                'hub' => $eventModel->isModuleEnabled('hub'),
            ],
            'links' => [
                'upload_url' => $settings->show_upload_button && $liveEnabled
                    ? $eventModel->publicUploadUrl()
                    : null,
                'upload_api_url' => $settings->show_upload_button && $liveEnabled
                    ? $eventModel->publicUploadApiUrl()
                    : null,
                'gallery_url' => $settings->show_gallery_button
                    ? url("/api/v1/public/events/{$eventModel->slug}/gallery")
                    : null,
                'wall_url' => $settings->show_wall_button && $wallEnabled
                    ? $eventModel->wallSettings?->publicUrl()
                    : null,
            ],
        ]);
    }
}
