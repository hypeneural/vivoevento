<?php

namespace App\Modules\Wall\Http\Controllers;

use App\Modules\Wall\Actions\ExpireWallAction;
use App\Modules\Wall\Actions\ResetWallAction;
use App\Modules\Wall\Actions\StartWallAction;
use App\Modules\Wall\Actions\StopWallAction;
use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Enums\WallTransition;
use App\Modules\Wall\Http\Requests\UpdateWallSettingsRequest;
use App\Modules\Wall\Http\Resources\WallSettingsResource;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallBroadcasterService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventWallController extends BaseController
{
    // ─── CRUD ─────────────────────────────────────────────

    /**
     * GET /events/{event}/wall/settings
     * Show wall settings for an event. Creates defaults if none exist.
     */
    public function show(int $event): JsonResponse
    {
        $settings = EventWallSetting::firstOrCreate(['event_id' => $event]);

        return $this->success(
            (new WallSettingsResource($settings))->resolve(),
        );
    }

    /**
     * PATCH /events/{event}/wall/settings
     * Update wall settings. Broadcasts changes instantly.
     */
    public function update(
        UpdateWallSettingsRequest $request,
        int $event,
        WallBroadcasterService $broadcaster,
    ): JsonResponse {
        $settings = EventWallSetting::firstOrCreate(['event_id' => $event]);

        $settings->update($request->validated());
        $settings->refresh();

        // Broadcast settings change to connected wall players
        $broadcaster->broadcastSettingsUpdated($settings);

        return $this->success(
            (new WallSettingsResource($settings))->resolve(),
        );
    }

    // ─── Status Controls ──────────────────────────────────

    /**
     * POST /events/{event}/wall/start
     * Start the wall — goes live instantly.
     */
    public function start(int $event, StartWallAction $action): JsonResponse
    {
        $settings = $action->execute($event);

        return $this->success([
            'message' => 'Wall iniciado',
            'status'  => $settings->status->value,
            'wall_code' => $settings->wall_code,
        ]);
    }

    /**
     * POST /events/{event}/wall/stop
     * Pause the wall — slides stop, screen stays.
     */
    public function stop(int $event, StopWallAction $action): JsonResponse
    {
        $settings = $action->execute($event, WallStatus::Paused);

        return $this->success([
            'message' => 'Wall pausado',
            'status'  => $settings->status->value,
        ]);
    }

    /**
     * POST /events/{event}/wall/pause
     * Alias for stop (pause).
     */
    public function pause(int $event, StopWallAction $action): JsonResponse
    {
        $settings = $action->execute($event, WallStatus::Paused);

        return $this->success([
            'message' => 'Wall pausado',
            'status'  => $settings->status->value,
        ]);
    }

    /**
     * POST /events/{event}/wall/full-stop
     * Full stop — disables the wall completely.
     */
    public function fullStop(int $event, StopWallAction $action): JsonResponse
    {
        $settings = $action->execute($event, WallStatus::Stopped);

        return $this->success([
            'message' => 'Wall parado completamente',
            'status'  => $settings->status->value,
        ]);
    }

    /**
     * POST /events/{event}/wall/expire
     * Expire the wall — terminal state.
     */
    public function expire(int $event, ExpireWallAction $action): JsonResponse
    {
        $settings = $action->execute($event);

        return $this->success([
            'message' => 'Wall expirado',
            'status'  => $settings->status->value,
        ]);
    }

    /**
     * POST /events/{event}/wall/reset
     * Reset wall to defaults — generates new code.
     */
    public function reset(int $event, ResetWallAction $action): JsonResponse
    {
        $settings = $action->execute($event);

        return $this->success([
            'message'   => 'Wall resetado',
            'status'    => $settings->status->value,
            'wall_code' => $settings->wall_code,
        ]);
    }

    // ─── Asset Uploads ────────────────────────────────────

    /**
     * POST /events/{event}/wall/upload-background
     * Upload a custom background image.
     */
    public function uploadBackground(
        Request $request,
        int $event,
        WallBroadcasterService $broadcaster,
    ): JsonResponse {
        $request->validate([
            'background' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event]);
        $file = $request->file('background');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = "wall/events/{$event}/backgrounds/" . Str::uuid() . ".{$ext}";

        // Delete previous background
        if ($settings->background_image_path) {
            Storage::disk('public')->delete($settings->background_image_path);
        }

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $settings->update(['background_image_path' => $path]);
        $settings->refresh();

        $broadcaster->broadcastSettingsUpdated($settings);

        return $this->success([
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * POST /events/{event}/wall/upload-logo
     * Upload a partner logo.
     */
    public function uploadLogo(
        Request $request,
        int $event,
        WallBroadcasterService $broadcaster,
    ): JsonResponse {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,svg,webp', 'max:2048'],
        ]);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event]);
        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = "wall/events/{$event}/partner-logos/" . Str::uuid() . ".{$ext}";

        // Delete previous logo
        if ($settings->partner_logo_path) {
            Storage::disk('public')->delete($settings->partner_logo_path);
        }

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $settings->update(['partner_logo_path' => $path]);
        $settings->refresh();

        $broadcaster->broadcastSettingsUpdated($settings);

        return $this->success([
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ]);
    }

    // ─── Options (enums for admin forms) ──────────────────

    /**
     * GET /events/{event}/wall/options
     * Returns available options for the admin form.
     */
    public function options(): JsonResponse
    {
        return $this->success([
            'layouts'     => collect(WallLayout::cases())->map(fn ($l) => [
                'value' => $l->value, 'label' => $l->label(),
            ]),
            'transitions' => collect(WallTransition::cases())->map(fn ($t) => [
                'value' => $t->value, 'label' => $t->label(),
            ]),
            'statuses'    => collect(WallStatus::cases())->map(fn ($s) => [
                'value' => $s->value, 'label' => $s->label(),
            ]),
        ]);
    }
}
