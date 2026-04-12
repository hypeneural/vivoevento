<?php

namespace App\Modules\EventPeople\Http\Controllers;

use App\Modules\EventPeople\Enums\EventRelationalCollectionStatus;
use App\Modules\EventPeople\Enums\EventRelationalCollectionVisibility;
use App\Modules\EventPeople\Http\Resources\PublicEventRelationalCollectionResource;
use App\Modules\EventPeople\Models\EventRelationalCollection;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PublicEventRelationalCollectionsController extends BaseController
{
    public function show(string $token): JsonResponse
    {
        $collection = EventRelationalCollection::query()
            ->with([
                'event.organization',
                'personA',
                'personB',
                'group',
                'items' => fn ($query) => $query
                    ->where('is_published', true)
                    ->with('media')
                    ->orderBy('sort_order'),
            ])
            ->where('share_token', $token)
            ->where('visibility', EventRelationalCollectionVisibility::PublicReady->value)
            ->where('status', EventRelationalCollectionStatus::Active->value)
            ->firstOrFail();

        abort_unless($collection->event, 404);

        if (! $collection->event->isActive()) {
            return $this->error('Entrega publica indisponivel no momento.', 410);
        }

        return $this->success([
            'event' => [
                'id' => $collection->event->id,
                'title' => $collection->event->title,
                'slug' => $collection->event->slug,
                'event_type' => $collection->event->event_type,
                'starts_at' => $collection->event->starts_at?->toIso8601String(),
                'location_name' => $collection->event->location_name,
                'public_gallery_url' => $collection->event->publicGalleryUrl(),
                'public_hub_url' => $collection->event->publicHubUrl(),
            ],
            'collection' => PublicEventRelationalCollectionResource::make($collection),
        ]);
    }
}
