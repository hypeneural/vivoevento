<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Http\Requests\RegenerateEventPublicLinksRequest;
use App\Modules\Events\Http\Requests\UpdateEventPublicLinksRequest;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventPublicLinksService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventQrController extends BaseController
{
    public function generateQr(Event $event, EventPublicLinksService $links): JsonResponse
    {
        $this->authorize('view', $event);

        $event = $links->sync($event);
        $payload = $links->links($event);

        return $this->success([
            'upload_url' => $event->publicUploadUrl(),
            'upload_api_url' => $event->publicUploadApiUrl(),
            'upload_slug' => $event->upload_slug,
            'public_url' => $event->publicHubUrl(),
            'qr_code_path' => $event->qr_code_path,
            'links' => $payload['links'],
            'identifiers' => $payload['identifiers'],
        ]);
    }

    public function shareLinks(Event $event, EventPublicLinksService $links): JsonResponse
    {
        $this->authorize('view', $event);

        $event = $links->sync($event);
        $payload = $links->links($event);

        return $this->success([
            'public_url' => $event->publicHubUrl(),
            'upload_url' => $event->publicUploadUrl(),
            'upload_api_url' => $event->publicUploadApiUrl(),
            'gallery_url' => $event->publicGalleryUrl(),
            'wall_url' => $payload['links']['wall']['url'] ?? null,
            'hub_url' => $event->publicHubUrl(),
            'play_url' => $event->publicPlayUrl(),
            'upload_slug' => $event->upload_slug,
            'links' => $payload['links'],
            'identifiers' => $payload['identifiers'],
        ]);
    }

    public function updateIdentifiers(
        UpdateEventPublicLinksRequest $request,
        Event $event,
        EventPublicLinksService $links,
    ): JsonResponse {
        $this->authorize('update', $event);

        $event = $links->updateIdentifiers($event, $request->validated());
        $payload = $links->links($event);

        return $this->success([
            'links' => $payload['links'],
            'identifiers' => $payload['identifiers'],
        ]);
    }

    public function regenerateIdentifiers(
        RegenerateEventPublicLinksRequest $request,
        Event $event,
        EventPublicLinksService $links,
    ): JsonResponse {
        $this->authorize('update', $event);

        $event = $links->regenerate($event, $request->validated('fields'));
        $payload = $links->links($event);

        return $this->success([
            'links' => $payload['links'],
            'identifiers' => $payload['identifiers'],
        ]);
    }
}
