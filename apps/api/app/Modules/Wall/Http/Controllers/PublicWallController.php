<?php

namespace App\Modules\Wall\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\Wall\Http\Resources\WallBootResource;
use App\Modules\Wall\Models\EventWallSetting;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class PublicWallController extends BaseController
{
    /**
     * GET /public/wall/{wallCode}/boot
     *
     * Bootstrap the wall player with event info, settings, and initial media list.
     * Called once when the wall screen loads.
     */
    public function boot(string $wallCode): JsonResponse
    {
        $settings = EventWallSetting::query()
            ->with('event')
            ->where('wall_code', $wallCode)
            ->first();

        if (! $settings || ! $settings->event) {
            return $this->error('Wall não encontrado.', 404);
        }

        if (! $settings->isAvailable()) {
            return $this->error('Este wall não está disponível.', 410);
        }

        // Load media if wall is playable
        $media = $settings->isPlayable()
            ? $this->loadWallMedia($settings)
            : new Collection();

        $settings->event->setRelation('media', $media);

        return $this->success(
            (new WallBootResource($settings))->resolve(),
        );
    }

    /**
     * GET /public/wall/{wallCode}/state
     *
     * Lightweight endpoint to check current wall status.
     * Used by the wall player for reconnection checks.
     */
    public function state(string $wallCode): JsonResponse
    {
        $settings = EventWallSetting::query()
            ->with('event')
            ->where('wall_code', $wallCode)
            ->first();

        if (! $settings || ! $settings->event) {
            return $this->error('Wall não encontrado.', 404);
        }

        return $this->success([
            'status'    => $settings->publicStatus(),
            'is_live'   => $settings->isLive(),
            'wall_code' => $settings->wall_code,
        ]);
    }

    /**
     * Load media eligible for the wall.
     * Approved + Published + ordered by most recent.
     */
    private function loadWallMedia(EventWallSetting $settings): Collection
    {
        return $settings->event->media()
            ->where('moderation_status', ModerationStatus::Approved)
            ->where('publication_status', PublicationStatus::Published)
            ->whereIn('media_type', ['image', 'video'])
            ->with('variants')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(max(1, (int) $settings->queue_limit))
            ->get();
    }
}
