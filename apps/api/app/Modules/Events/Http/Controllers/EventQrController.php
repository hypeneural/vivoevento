<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventQrController extends BaseController
{
    public function generateQr(Event $event): JsonResponse
    {
        $uploadUrl = $event->publicUploadUrl();

        $event->update([
            'upload_url' => $uploadUrl,
            'public_url' => config('app.url') . '/e/' . $event->slug,
        ]);

        return $this->success([
            'upload_url' => $uploadUrl,
            'upload_api_url' => $event->publicUploadApiUrl(),
            'upload_slug' => $event->upload_slug,
            'public_url' => $event->public_url,
            'qr_code_path' => $event->qr_code_path,
        ]);
    }

    public function shareLinks(Event $event): JsonResponse
    {
        return $this->success([
            'public_url' => $event->public_url,
            'upload_url' => $event->upload_url,
            'upload_api_url' => $event->publicUploadApiUrl(),
            'gallery_url' => config('app.url') . '/e/' . $event->slug . '/gallery',
            'wall_url' => config('app.url') . '/e/' . $event->slug . '/wall',
            'hub_url' => config('app.url') . '/e/' . $event->slug . '/hub',
            'upload_slug' => $event->upload_slug,
        ]);
    }
}
