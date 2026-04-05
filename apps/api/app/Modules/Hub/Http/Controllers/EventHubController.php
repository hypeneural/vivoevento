<?php
namespace App\Modules\Hub\Http\Controllers;
use App\Modules\Events\Models\Event;
use App\Modules\Hub\Actions\UploadHubHeroImageAction;
use App\Modules\Hub\Actions\UploadHubSponsorLogoAction;
use App\Modules\Hub\Actions\UpdateEventHubSettingsAction;
use App\Modules\Hub\Http\Requests\ShowEventHubInsightsRequest;
use App\Modules\Hub\Http\Requests\UploadHubHeroImageRequest;
use App\Modules\Hub\Http\Requests\UploadHubSponsorLogoRequest;
use App\Modules\Hub\Http\Requests\UpdateEventHubSettingsRequest;
use App\Modules\Hub\Http\Resources\EventHubSettingsResource;
use App\Modules\Hub\Queries\GetEventHubInsightsQuery;
use App\Modules\Hub\Support\HubPayloadFactory;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventHubController extends BaseController
{
    public function show(Event $event, HubPayloadFactory $payloads): JsonResponse
    {
        $this->authorize('view', $event);

        $settings = $payloads->ensureSettings($event);

        return $this->success(
            (new EventHubSettingsResource($settings, $event))->resolve(),
        );
    }

    public function insights(
        ShowEventHubInsightsRequest $request,
        Event $event,
        HubPayloadFactory $payloads,
        GetEventHubInsightsQuery $query,
    ): JsonResponse {
        $this->authorize('view', $event);

        $settings = $payloads->ensureSettings($event);
        $insights = $query->execute($event, $settings, $request->integer('days', 30));

        return $this->success($insights);
    }

    public function update(
        UpdateEventHubSettingsRequest $request,
        Event $event,
        HubPayloadFactory $payloads,
        UpdateEventHubSettingsAction $action,
    ): JsonResponse {
        $this->authorize('update', $event);

        $settings = $payloads->ensureSettings($event);
        $settings = $action->execute($settings, $request->validated());

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id])
            ->log('Hub do evento atualizado');

        return $this->success(
            (new EventHubSettingsResource($settings, $event))->resolve(),
        );
    }

    public function uploadHeroImage(
        UploadHubHeroImageRequest $request,
        Event $event,
        HubPayloadFactory $payloads,
        UploadHubHeroImageAction $action,
    ): JsonResponse {
        $this->authorize('update', $event);

        $settings = $payloads->ensureSettings($event);
        $asset = $action->execute(
            $event,
            $settings,
            $request->file('file'),
            $request->validated('previous_path'),
        );

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id, 'hero_image_path' => $asset['path']])
            ->log('Hero image do hub atualizada');

        return $this->success($asset);
    }

    public function uploadSponsorLogo(
        UploadHubSponsorLogoRequest $request,
        Event $event,
        UploadHubSponsorLogoAction $action,
    ): JsonResponse {
        $this->authorize('update', $event);

        $asset = $action->execute(
            $event,
            $request->file('file'),
            $request->validated('previous_path'),
        );

        activity()
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['event_id' => $event->id, 'sponsor_logo_path' => $asset['path']])
            ->log('Logo de parceiro do hub enviada');

        return $this->success($asset);
    }
}
