<?php

namespace App\Modules\FaceSearch\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventBrandingResolver;
use App\Modules\FaceSearch\Actions\SearchFacesBySelfieAction;
use App\Modules\FaceSearch\Http\Requests\SearchPublicEventFaceSearchRequest;
use App\Modules\FaceSearch\Http\Resources\EventFaceSearchRequestResource;
use App\Modules\FaceSearch\Http\Resources\FaceSearchMatchResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventFaceSearchController extends BaseController
{
    public function show(string $event, Request $request, AnalyticsTracker $analytics): JsonResponse
    {
        $eventModel = Event::with(['modules', 'faceSearchSettings', 'organization'])
            ->where('slug', $event)
            ->firstOrFail();

        $analytics->trackEvent(
            $eventModel,
            'face_search.page_view',
            $request,
            ['surface' => 'find_me'],
            channel: 'face-search',
        );

        $availability = $this->availabilityFor($eventModel);
        $branding = app(EventBrandingResolver::class)->resolve($eventModel);

        return $this->success([
            'event' => [
                'id' => $eventModel->id,
                'title' => $eventModel->title,
                'slug' => $eventModel->slug,
                'cover_image_path' => $branding['cover_image_path'],
                'cover_image_url' => $branding['cover_image_url'],
                'logo_path' => $branding['logo_path'],
                'logo_url' => $branding['logo_url'],
                'primary_color' => $branding['primary_color'],
                'secondary_color' => $branding['secondary_color'],
                'effective_branding' => $branding,
                'starts_at' => $eventModel->starts_at?->toISOString(),
                'location_name' => $eventModel->location_name,
            ],
            'search' => [
                'enabled' => $availability['enabled'],
                'status' => $availability['status'],
                'reason' => $availability['reason'],
                'message' => $availability['message'],
                'instructions' => 'Envie uma selfie com apenas uma pessoa visivel para localizar fotos publicadas neste evento.',
                'consent_required' => true,
                'consent_version' => 'v1',
                'selfie_retention_hours' => (int) ($eventModel->faceSearchSettings?->selfie_retention_hours ?? 24),
                'top_k' => (int) ($eventModel->faceSearchSettings?->top_k ?? config('face_search.top_k', 50)),
            ],
            'links' => [
                'find_me_url' => $eventModel->publicFindMeUrl(),
                'find_me_api_url' => $eventModel->publicFindMeApiUrl(),
                'gallery_url' => $eventModel->publicGalleryUrl(),
                'hub_url' => $eventModel->publicHubUrl(),
            ],
        ]);
    }

    public function store(
        SearchPublicEventFaceSearchRequest $request,
        string $event,
        SearchFacesBySelfieAction $action,
        AnalyticsTracker $analytics,
    ): JsonResponse {
        $eventModel = Event::with(['modules', 'faceSearchSettings', 'organization'])
            ->where('slug', $event)
            ->firstOrFail();

        $availability = $this->availabilityFor($eventModel);

        if (! $availability['enabled']) {
            return $this->error(
                $availability['message'],
                422,
                ['event' => [$availability['reason']]],
            );
        }

        $result = $action->execute(
            event: $eventModel,
            selfie: $request->file('selfie'),
            requesterType: 'guest',
            consentVersion: (string) $request->string('consent_version'),
            selfieStorageStrategy: (string) $request->input('selfie_storage_strategy', 'memory_only'),
            publicSearch: true,
            includePending: false,
        );

        $analytics->trackEvent(
            $eventModel,
            'face_search.requested',
            $request,
            [
                'surface' => 'find_me',
                'face_search_request_id' => $result['request']->id,
                'result_count' => count($result['results']),
            ],
            channel: 'face-search',
        );

        return $this->success([
            'request' => new EventFaceSearchRequestResource($result['request']),
            'total_results' => count($result['results']),
            'results' => FaceSearchMatchResource::collection(collect($result['results'])),
        ]);
    }

    /**
     * @return array{enabled:bool,status:string,reason:string|null,message:string}
     */
    private function availabilityFor(Event $event): array
    {
        if (! $event->isModuleEnabled('live')) {
            return [
                'enabled' => false,
                'status' => 'disabled',
                'reason' => 'live_module_disabled',
                'message' => 'A busca por selfie ainda nao foi habilitada para este evento.',
            ];
        }

        if (! $event->isActive()) {
            return [
                'enabled' => false,
                'status' => 'inactive',
                'reason' => 'event_inactive',
                'message' => 'A busca por selfie esta temporariamente indisponivel.',
            ];
        }

        if (! $event->isFaceSearchEnabled()) {
            return [
                'enabled' => false,
                'status' => 'disabled',
                'reason' => 'face_search_disabled',
                'message' => 'A busca por selfie nao esta ativa para este evento.',
            ];
        }

        if (! $event->allowsPublicSelfieSearch()) {
            return [
                'enabled' => false,
                'status' => 'disabled',
                'reason' => 'public_face_search_disabled',
                'message' => 'A busca publica por selfie nao esta disponivel para este evento.',
            ];
        }

        return [
            'enabled' => true,
            'status' => 'available',
            'reason' => null,
            'message' => 'Envie uma selfie nitida para encontrar suas fotos ja publicadas.',
        ];
    }

    private function publicAssetUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return url(\Storage::disk('public')->url($path));
    }
}
