<?php

namespace App\Modules\Wall\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Http\Requests\StoreWallAdRequest;
use App\Modules\Wall\Models\EventWallAd;
use App\Modules\Wall\Services\WallBroadcasterService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventWallAdController extends BaseController
{
    /**
     * GET /events/{event}/wall/ads
     * List all ads for this event's wall.
     */
    public function index(Event $event): JsonResponse
    {
        $this->authorize('viewWall', $event);

        $wallSetting = $event->wallSettings;

        if (! $wallSetting) {
            return $this->error('Wall nao configurado.', 404);
        }

        $ads = $wallSetting->ads()
            ->orderBy('position')
            ->get()
            ->map(fn (EventWallAd $ad) => $this->formatAd($ad));

        return $this->success($ads);
    }

    /**
     * POST /events/{event}/wall/ads
     * Upload a new ad.
     */
    public function store(StoreWallAdRequest $request, Event $event): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $wallSetting = $event->wallSettings;

        if (! $wallSetting) {
            return $this->error('Wall nao configurado.', 404);
        }

        $file = $request->file('file');
        [$mediaType, $extension] = $this->resolveUploadedAdMetadata($file);

        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs(
            "wall/events/{$event->id}/ads",
            $filename,
            'public',
        );

        $maxPosition = $wallSetting->ads()->max('position') ?? -1;

        $ad = $wallSetting->ads()->create([
            'file_path' => $path,
            'media_type' => $mediaType,
            'duration_seconds' => $request->input('duration_seconds', $mediaType === 'video' ? 0 : 10),
            'position' => $maxPosition + 1,
            'is_active' => true,
        ]);

        $this->broadcastAdsUpdate($wallSetting);

        return $this->success($this->formatAd($ad), 201);
    }

    /**
     * DELETE /events/{event}/wall/ads/{ad}
     * Delete an ad.
     */
    public function destroy(Event $event, EventWallAd $ad): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $wallSetting = $event->wallSettings;

        if (! $wallSetting || $ad->event_wall_setting_id !== $wallSetting->id) {
            return $this->error('Anuncio nao encontrado.', 404);
        }

        if ($ad->file_path && Storage::disk('public')->exists($ad->file_path)) {
            Storage::disk('public')->delete($ad->file_path);
        }

        $ad->delete();

        $this->broadcastAdsUpdate($wallSetting);

        return $this->success(null, 204);
    }

    /**
     * PATCH /events/{event}/wall/ads/reorder
     * Reorder ads.
     */
    public function reorder(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $wallSetting = $event->wallSettings;

        if (! $wallSetting) {
            return $this->error('Wall nao configurado.', 404);
        }

        $request->validate([
            'order' => 'required|array|min:1',
            'order.*' => 'integer|distinct',
        ]);

        $order = array_values($request->input('order'));
        $ownedIds = $wallSetting->ads()
            ->whereIn('id', $order)
            ->pluck('id')
            ->all();

        if (count($ownedIds) !== count($order)) {
            throw ValidationException::withMessages([
                'order' => 'A ordem informada contem anuncios que nao pertencem a este telao.',
            ]);
        }

        foreach ($order as $position => $adId) {
            EventWallAd::where('id', $adId)
                ->where('event_wall_setting_id', $wallSetting->id)
                ->update(['position' => $position]);
        }

        $this->broadcastAdsUpdate($wallSetting);

        return $this->success(['reordered' => true]);
    }

    private function broadcastAdsUpdate($wallSetting): void
    {
        app(WallBroadcasterService::class)->broadcastAdsUpdated($wallSetting);
    }

    private function formatAd(EventWallAd $ad): array
    {
        return [
            'id' => $ad->id,
            'url' => $ad->file_path ? Storage::disk('public')->url($ad->file_path) : null,
            'media_type' => $ad->media_type,
            'duration_seconds' => $ad->duration_seconds,
            'position' => $ad->position,
            'is_active' => $ad->is_active,
            'created_at' => $ad->created_at?->toIso8601String(),
        ];
    }

    /**
     * Detect the ad media type from the real MIME and map it to a safe extension.
     */
    private function resolveUploadedAdMetadata(UploadedFile $file): array
    {
        $mimeMap = [
            'image/jpeg' => ['image', 'jpg'],
            'image/png' => ['image', 'png'],
            'image/webp' => ['image', 'webp'],
            'image/gif' => ['image', 'gif'],
            'video/mp4' => ['video', 'mp4'],
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo ? finfo_file($finfo, $file->getPathname()) : false;

        if ($finfo) {
            finfo_close($finfo);
        }

        if (! is_string($realMime) || ! array_key_exists($realMime, $mimeMap)) {
            throw ValidationException::withMessages([
                'file' => 'Tipo de arquivo nao permitido.',
            ]);
        }

        return $mimeMap[$realMime];
    }
}
