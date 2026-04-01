<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Actions\CreateEventAction;
use App\Modules\Events\Actions\UpdateEventAction;
use App\Modules\Events\Http\Requests\StoreEventRequest;
use App\Modules\Events\Http\Requests\UpdateEventRequest;
use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Queries\ListEventsQuery;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = new ListEventsQuery(
            organizationId: $request->query('organization_id'),
            status: $request->query('status'),
            search: $request->query('search'),
        );

        $events = $query->query()->paginate(
            $request->input('per_page', 20)
        );

        return $this->paginated(EventResource::collection($events));
    }

    public function store(StoreEventRequest $request, CreateEventAction $action): JsonResponse
    {
        $event = $action->execute($request->validated(), $request->user()->id);

        // Rich response with links and QR status
        return $this->success([
            'id' => $event->id,
            'uuid' => $event->uuid,
            'title' => $event->title,
            'status' => $event->status->value,
            'slug' => $event->slug,
            'upload_slug' => $event->upload_slug,
            'public_url' => $event->public_url,
            'upload_url' => $event->upload_url,
            'upload_api_url' => $event->publicUploadApiUrl(),
            'modules' => $event->modules->pluck('is_enabled', 'module_key'),
            'links' => [
                'public_hub' => $event->public_url,
                'upload' => $event->upload_url,
                'upload_api' => $event->publicUploadApiUrl(),
                'wall' => config('app.url') . '/w/' . $event->slug,
            ],
            'qr' => [
                'status' => $event->qr_code_path ? 'ready' : 'pending',
                'image_url' => $event->qr_code_path,
            ],
        ], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load([
            'modules', 'channels', 'banners', 'client',
            'teamMembers.user:id,name,email',
            'wallSettings', 'playSettings', 'hubSettings',
        ]);
        $event->loadCount('media');

        return $this->success(new EventResource($event));
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEventAction $action): JsonResponse
    {
        $event = $action->execute($event, $request->validated());

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id])
            ->log('Evento atualizado');

        return $this->success(new EventResource($event->fresh()->load('modules')));
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return $this->noContent();
    }

    /**
     * PATCH /api/v1/events/{event}/moderation-settings
     */
    public function updateModerationSettings(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'moderation_mode' => ['required', 'string', 'in:manual,auto'],
        ]);

        $event->update($validated);

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id, 'moderation_mode' => $validated['moderation_mode']])
            ->log('Modo de moderação alterado para ' . $validated['moderation_mode']);

        return $this->success(new EventResource($event->fresh()));
    }
}
