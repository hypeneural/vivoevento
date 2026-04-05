<?php

namespace App\Modules\Wall\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Wall\Http\Requests\WallHeartbeatRequest;
use App\Modules\Wall\Http\Resources\WallBootResource;
use App\Modules\Wall\Jobs\RecalculateWallDiagnosticsJob;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallDiagnosticsService;
use App\Modules\Wall\Services\WallRuntimeMediaService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicWallController extends BaseController
{
    public function boot(
        string $wallCode,
        Request $request,
        AnalyticsTracker $analytics,
        WallRuntimeMediaService $runtimeMedia,
    ): JsonResponse {
        $settings = $this->resolveWallSetting($wallCode);

        if ($settings instanceof JsonResponse) {
            return $settings;
        }

        $media = $settings->isPlayable()
            ? $runtimeMedia->loadPlayableMedia($settings)
            : collect();

        $settings->event->setRelation('media', $media);

        $analytics->trackEvent(
            $settings->event,
            'wall.page_view',
            $request,
            ['surface' => 'wall', 'wall_code' => $settings->wall_code],
            channel: 'wall',
        );

        return $this->success(
            (new WallBootResource($settings))->resolve(),
        );
    }

    public function state(string $wallCode): JsonResponse
    {
        $settings = $this->resolveWallSetting($wallCode, requireAvailable: false);

        if ($settings instanceof JsonResponse) {
            return $settings;
        }

        return $this->success([
            'status' => $settings->publicStatus(),
            'is_live' => $settings->isLive(),
            'wall_code' => $settings->wall_code,
        ]);
    }

    public function heartbeat(
        WallHeartbeatRequest $request,
        string $wallCode,
        WallDiagnosticsService $diagnostics,
    ): JsonResponse {
        $settings = $this->resolveWallSetting($wallCode);

        if ($settings instanceof JsonResponse) {
            return $settings;
        }

        $diagnostics->recordHeartbeat($settings, $request->validated());
        RecalculateWallDiagnosticsJob::dispatch($settings->id);

        return $this->success([
            'acknowledged_at' => now()->toIso8601String(),
        ]);
    }

    private function resolveWallSetting(string $wallCode, bool $requireAvailable = true): EventWallSetting|JsonResponse
    {
        $settings = EventWallSetting::query()
            ->with('event')
            ->where('wall_code', $wallCode)
            ->first();

        if (! $settings || ! $settings->event) {
            return $this->error('Wall nao encontrado.', 404);
        }

        if ($requireAvailable && ! $settings->isAvailable()) {
            return $this->error('Este wall nao esta disponivel.', 410);
        }

        return $settings;
    }
}
