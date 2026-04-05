<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Actions\CreateEventAction;
use App\Modules\Events\Actions\UpdateEventAction;
use App\Modules\Events\Http\Requests\ListEventsRequest;
use App\Modules\Events\Http\Requests\StoreEventRequest;
use App\Modules\Events\Http\Requests\UpdateEventRequest;
use App\Modules\Events\Http\Resources\EventCommercialStatusResource;
use App\Modules\Events\Http\Resources\EventDetailResource;
use App\Modules\Events\Http\Resources\EventListResource;
use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Queries\ListEventsQuery;
use App\Modules\Events\Support\EventCommercialStatusService;
use App\Modules\Events\Support\EventPublicLinksService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends BaseController
{
    public function index(ListEventsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        $validated = $request->validated();

        $query = new ListEventsQuery(
            organizationId: $validated['organization_id'] ?? $request->user()?->currentOrganization()?->id,
            clientId: $validated['client_id'] ?? null,
            status: $validated['status'] ?? null,
            eventType: $validated['event_type'] ?? null,
            module: $validated['module'] ?? null,
            search: $validated['search'] ?? null,
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
            sortBy: $validated['sort_by'] ?? 'starts_at',
            sortDirection: $validated['sort_direction'] ?? 'desc',
        );

        $events = $query->query()->paginate(
            $validated['per_page'] ?? 15
        );

        return $this->paginated(EventListResource::collection($events));
    }

    public function store(
        StoreEventRequest $request,
        CreateEventAction $action,
        EventPublicLinksService $links,
    ): JsonResponse {
        $this->authorize('create', Event::class);

        $event = $links->sync(
            $action->execute($request->validated(), $request->user()->id)
        );
        $payload = $links->links($event);

        return $this->success([
            'id' => $event->id,
            'uuid' => $event->uuid,
            'title' => $event->title,
            'status' => $event->status->value,
            'moderation_mode' => $event->moderation_mode?->value,
            'commercial_mode' => $event->commercial_mode?->value,
            'current_entitlements' => $event->current_entitlements_json,
            'face_search' => $event->faceSearchSettings ? [
                'id' => $event->faceSearchSettings->id,
                'event_id' => $event->faceSearchSettings->event_id,
                'provider_key' => $event->faceSearchSettings->provider_key,
                'embedding_model_key' => $event->faceSearchSettings->embedding_model_key,
                'vector_store_key' => $event->faceSearchSettings->vector_store_key,
                'enabled' => (bool) $event->faceSearchSettings->enabled,
                'min_face_size_px' => $event->faceSearchSettings->min_face_size_px,
                'min_quality_score' => $event->faceSearchSettings->min_quality_score,
                'search_threshold' => $event->faceSearchSettings->search_threshold,
                'top_k' => $event->faceSearchSettings->top_k,
                'allow_public_selfie_search' => (bool) $event->faceSearchSettings->allow_public_selfie_search,
                'selfie_retention_hours' => $event->faceSearchSettings->selfie_retention_hours,
                'created_at' => $event->faceSearchSettings->created_at?->toISOString(),
                'updated_at' => $event->faceSearchSettings->updated_at?->toISOString(),
            ] : null,
            'slug' => $event->slug,
            'upload_slug' => $event->upload_slug,
            'public_url' => $event->publicHubUrl(),
            'upload_url' => $event->publicUploadUrl(),
            'upload_api_url' => $event->publicUploadApiUrl(),
            'modules' => $event->modules->pluck('is_enabled', 'module_key'),
            'links' => [
                'public_hub' => $event->publicHubUrl(),
                'upload' => $event->publicUploadUrl(),
                'upload_api' => $event->publicUploadApiUrl(),
                'wall' => $payload['links']['wall']['url'] ?? null,
            ],
            'qr' => [
                'status' => $event->qr_code_path ? 'ready' : 'pending',
                'image_url' => $event->qr_code_path,
            ],
        ], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event->load([
            'organization',
            'modules',
            'channels',
            'banners',
            'client',
            'teamMembers.user:id,name,email',
            'wallSettings',
            'playSettings',
            'hubSettings',
            'contentModerationSettings',
            'faceSearchSettings',
            'mediaIntelligenceSettings',
        ]);
        $event->loadCount([
            'media',
            'media as pending_media_count' => fn ($query) => $query->where('moderation_status', 'pending'),
            'media as approved_media_count' => fn ($query) => $query->where('moderation_status', 'approved'),
            'media as published_media_count' => fn ($query) => $query->where('publication_status', 'published'),
        ]);

        return $this->success(new EventDetailResource($event));
    }

    public function commercialStatus(
        Event $event,
        EventCommercialStatusService $commercialStatus,
    ): JsonResponse {
        $this->authorize('view', $event);

        $event = $commercialStatus->sync($event);

        return $this->success(
            new EventCommercialStatusResource(
                $commercialStatus->build($event)
            )
        );
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEventAction $action): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $action->execute($event, $request->validated());

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id])
            ->log('Evento atualizado');

        return $this->success(new EventResource($event->fresh()->load(['modules', 'faceSearchSettings', 'mediaIntelligenceSettings'])->loadCount('media')));
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return $this->noContent();
    }

    public function updateModerationSettings(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'moderation_mode' => ['required', 'string', 'in:none,manual,ai'],
        ]);

        $event->update($validated);

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id, 'moderation_mode' => $validated['moderation_mode']])
            ->log('Modo de moderacao alterado para ' . $validated['moderation_mode']);

        return $this->success(new EventResource($event->fresh()));
    }
}
