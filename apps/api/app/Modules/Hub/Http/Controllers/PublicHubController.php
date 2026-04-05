<?php

namespace App\Modules\Hub\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Hub\Actions\TrackPublicHubButtonClickAction;
use App\Modules\Events\Models\Event;
use App\Modules\Hub\Http\Requests\TrackPublicHubClickRequest;
use App\Modules\Hub\Http\Resources\PublicHubResource;
use App\Modules\Hub\Models\EventHubSetting;
use App\Modules\Hub\Support\HubPayloadFactory;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicHubController extends BaseController
{
    public function index(
        Request $request,
        string $event,
        HubPayloadFactory $payloads,
        AnalyticsTracker $analytics,
    ): JsonResponse
    {
        [$eventModel, $settings] = $this->resolvePublicHubOrFail($event, $payloads);

        $analytics->trackEvent(
            $eventModel,
            'hub.page_view',
            $request,
            ['surface' => 'hub'],
            channel: 'hub',
        );

        return $this->success(
            (new PublicHubResource($settings, $eventModel))->resolve(),
        );
    }

    public function click(
        TrackPublicHubClickRequest $request,
        string $event,
        HubPayloadFactory $payloads,
        TrackPublicHubButtonClickAction $action,
    ): JsonResponse {
        [$eventModel, $settings] = $this->resolvePublicHub($event, $payloads);

        if (! $eventModel || ! $settings) {
            return $this->noContent();
        }

        $buttons = $payloads->clickTargets($eventModel, $settings);

        $action->execute(
            $eventModel,
            $request,
            is_array($buttons) ? $buttons : [],
            $request->validated('button_id'),
        );

        return $this->noContent();
    }

    /**
     * @return array{0: Event, 1: EventHubSetting}
     */
    private function resolvePublicHubOrFail(string $slug, HubPayloadFactory $payloads): array
    {
        $eventModel = Event::with(['modules', 'wallSettings'])
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $eventModel->isModuleEnabled('hub')) {
            abort(404, 'Hub publico indisponivel para este evento.');
        }

        $settings = $payloads->ensureSettings($eventModel);

        if (! $settings->is_enabled) {
            abort(404, 'Hub publico indisponivel para este evento.');
        }

        return [$eventModel, $settings];
    }

    /**
     * @return array{0: Event|null, 1: EventHubSetting|null}
     */
    private function resolvePublicHub(string $slug, HubPayloadFactory $payloads): array
    {
        $eventModel = Event::with(['modules', 'wallSettings'])
            ->where('slug', $slug)
            ->first();

        if (! $eventModel || ! $eventModel->isModuleEnabled('hub')) {
            return [null, null];
        }

        $settings = $payloads->ensureSettings($eventModel);

        if (! $settings->is_enabled) {
            return [null, null];
        }

        return [$eventModel, $settings];
    }
}
