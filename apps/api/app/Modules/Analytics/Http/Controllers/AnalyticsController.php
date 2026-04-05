<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Http\Requests\ShowEventAnalyticsRequest;
use App\Modules\Analytics\Http\Requests\ShowPlatformAnalyticsRequest;
use App\Modules\Analytics\Http\Resources\EventAnalyticsResource;
use App\Modules\Analytics\Http\Resources\PlatformAnalyticsResource;
use App\Modules\Analytics\Queries\BuildEventAnalyticsQuery;
use App\Modules\Analytics\Queries\BuildPlatformAnalyticsQuery;
use App\Modules\Analytics\Services\AnalyticsMetricsService;
use App\Modules\Analytics\Support\AnalyticsPeriodResolver;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends BaseController
{
    public function eventOverview(
        ShowEventAnalyticsRequest $request,
        Event $event,
        AnalyticsPeriodResolver $periodResolver,
        AnalyticsMetricsService $metrics,
    ): JsonResponse {
        $this->authorize('view', $event);

        $validated = $request->validated();
        $period = $periodResolver->resolve(
            preset: $validated['period'] ?? '30d',
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
            timezone: $request->user()?->currentOrganization()?->timezone,
        );

        $cacheKey = $this->cacheKey('event', $request->user(), [
            'event_id' => $event->id,
            'filters' => [
                ...$validated,
                ...$period->filters(),
            ],
        ]);

        $payload = Cache::remember($cacheKey, now()->addSeconds(60), fn () => (new BuildEventAnalyticsQuery(
            metrics: $metrics,
            event: $event,
            period: $period,
            module: $validated['module'] ?? null,
        ))->execute());

        return $this->success(new EventAnalyticsResource($payload));
    }

    public function platformOverview(
        ShowPlatformAnalyticsRequest $request,
        AnalyticsPeriodResolver $periodResolver,
        AnalyticsMetricsService $metrics,
    ): JsonResponse {
        $validated = $request->validated();
        $organizationId = $this->resolveOrganizationId($request->user(), $validated['organization_id'] ?? null);

        $period = $periodResolver->resolve(
            preset: $validated['period'] ?? '30d',
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
            timezone: $request->user()?->currentOrganization()?->timezone,
        );

        $cacheKey = $this->cacheKey('platform', $request->user(), [
            'filters' => [
                ...$validated,
                'organization_id' => $organizationId,
                ...$period->filters(),
            ],
        ]);

        $payload = Cache::remember($cacheKey, now()->addSeconds(60), fn () => (new BuildPlatformAnalyticsQuery(
            metrics: $metrics,
            period: $period,
            organizationId: $organizationId,
            clientId: isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            eventStatus: $validated['event_status'] ?? null,
            module: $validated['module'] ?? null,
        ))->execute());

        return $this->success(new PlatformAnalyticsResource($payload));
    }

    private function resolveOrganizationId(?User $user, ?int $organizationId): ?int
    {
        if ($user?->hasAnyRole(['super-admin', 'platform-admin'])) {
            return $organizationId;
        }

        $currentOrganizationId = $user?->currentOrganization()?->id;

        abort_unless($currentOrganizationId !== null, 422, 'Nenhuma organizacao ativa encontrada.');

        return $currentOrganizationId;
    }

    private function cacheKey(string $scope, ?User $user, array $payload): string
    {
        return 'analytics:' . $scope . ':' . ($user?->id ?? 'guest') . ':' . sha1(json_encode($payload));
    }
}
