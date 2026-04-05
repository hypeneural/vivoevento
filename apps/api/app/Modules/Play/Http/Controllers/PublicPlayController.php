<?php

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Events\Models\Event;
use App\Modules\Play\Http\Resources\PlayEventGameResource;
use App\Modules\Play\Models\EventPlaySetting;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicPlayController extends BaseController
{
    public function manifest(string $event, Request $request, AnalyticsTracker $analytics): JsonResponse
    {
        $eventModel = Event::with(['modules', 'playGames.gameType'])
            ->where('slug', $event)
            ->firstOrFail();

        $settings = EventPlaySetting::query()->where('event_id', $eventModel->id)->first();

        if (! $eventModel->isModuleEnabled('play') || ! $settings?->is_enabled) {
            return $this->error('Play publico indisponivel para este evento.', 404);
        }

        $games = $eventModel->playGames
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values();

        $analytics->trackEvent(
            $eventModel,
            'play.page_view',
            $request,
            ['surface' => 'play'],
            channel: 'play',
        );

        return $this->success([
            'event' => [
                'id' => $eventModel->id,
                'title' => $eventModel->title,
                'slug' => $eventModel->slug,
                'cover_image_url' => $this->assetUrl($eventModel->cover_image_path),
                'logo_url' => $this->assetUrl($eventModel->logo_path),
                'primary_color' => $eventModel->primary_color,
                'secondary_color' => $eventModel->secondary_color,
            ],
            'settings' => [
                'is_enabled' => (bool) $settings->is_enabled,
                'ranking_enabled' => (bool) $settings->ranking_enabled,
                'auto_refresh_assets' => (bool) $settings->auto_refresh_assets,
            ],
            'games' => PlayEventGameResource::collection($games),
            'pwa' => [
                'installable' => true,
                'min_version' => null,
            ],
        ]);
    }

    private function assetUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $url = Storage::disk('public')->url($path);

        return preg_match('/^https?:\/\//i', $url) === 1
            ? $url
            : url($url);
    }
}
