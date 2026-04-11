<?php

namespace App\Modules\Wall\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Actions\ExpireWallAction;
use App\Modules\Wall\Actions\ResetWallAction;
use App\Modules\Wall\Actions\StartWallAction;
use App\Modules\Wall\Actions\StopWallAction;
use App\Modules\Wall\Enums\WallEventPhase;
use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Enums\WallTransition;
use App\Modules\Wall\Enums\WallTransitionMode;
use App\Modules\Wall\Http\Requests\RunWallPlayerCommandRequest;
use App\Modules\Wall\Http\Requests\SimulateWallRequest;
use App\Modules\Wall\Http\Requests\UpdateWallSettingsRequest;
use App\Modules\Wall\Http\Resources\WallDiagnosticsResource;
use App\Modules\Wall\Http\Resources\WallSettingsResource;
use App\Modules\Wall\Http\Resources\WallSimulationResource;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallBroadcasterService;
use App\Modules\Wall\Services\WallDiagnosticsService;
use App\Modules\Wall\Services\WallSimulationService;
use App\Modules\Wall\Support\WallSelectionPreset;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventWallController extends BaseController
{
    public function show(Event $event): JsonResponse
    {
        $this->authorize('viewWall', $event);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event->id])->load(['diagnosticSummary', 'event']);

        return $this->success(
            (new WallSettingsResource($settings))->resolve(),
        );
    }

    public function update(
        UpdateWallSettingsRequest $request,
        Event $event,
        WallBroadcasterService $broadcaster,
    ): JsonResponse {
        $this->authorize('manageWall', $event);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event->id]);
        $data = $request->validated();

        if (array_key_exists('selection_mode', $data) || array_key_exists('selection_policy', $data)) {
            $mode = $data['selection_mode'] ?? $settings->selection_mode;
            $data['selection_policy'] = WallSelectionPreset::normalizePolicy(
                $data['selection_policy'] ?? $settings->selection_policy,
                $mode,
            );
        }

        $settings->update($data);
        $settings->refresh()->load(['diagnosticSummary', 'event']);

        $broadcaster->broadcastSettingsUpdated($settings);

        return $this->success(
            (new WallSettingsResource($settings))->resolve(),
        );
    }

    public function start(Event $event, StartWallAction $action): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $settings = $action->execute($event->id);

        return $this->success([
            'message' => 'Wall iniciado',
            'status' => $settings->status->value,
            'wall_code' => $settings->wall_code,
        ]);
    }

    public function stop(Event $event, StopWallAction $action): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $settings = $action->execute($event->id, WallStatus::Paused);

        return $this->success([
            'message' => 'Wall pausado',
            'status' => $settings->status->value,
        ]);
    }

    public function pause(Event $event, StopWallAction $action): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $settings = $action->execute($event->id, WallStatus::Paused);

        return $this->success([
            'message' => 'Wall pausado',
            'status' => $settings->status->value,
        ]);
    }

    public function fullStop(Event $event, StopWallAction $action): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $settings = $action->execute($event->id, WallStatus::Stopped);

        return $this->success([
            'message' => 'Wall parado completamente',
            'status' => $settings->status->value,
        ]);
    }

    public function expire(Event $event, ExpireWallAction $action): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $settings = $action->execute($event->id);

        return $this->success([
            'message' => 'Wall expirado',
            'status' => $settings->status->value,
        ]);
    }

    public function reset(Event $event, ResetWallAction $action): JsonResponse
    {
        $this->authorize('manageWall', $event);

        $settings = $action->execute($event->id);

        return $this->success([
            'message' => 'Wall resetado',
            'status' => $settings->status->value,
            'wall_code' => $settings->wall_code,
        ]);
    }

    public function uploadBackground(
        Request $request,
        Event $event,
        WallBroadcasterService $broadcaster,
    ): JsonResponse {
        $this->authorize('manageWall', $event);

        $request->validate([
            'background' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event->id]);
        $file = $request->file('background');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = "wall/events/{$event->id}/backgrounds/".Str::uuid().".{$ext}";

        if ($settings->background_image_path) {
            Storage::disk('public')->delete($settings->background_image_path);
        }

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $settings->update(['background_image_path' => $path]);
        $settings->refresh()->load('diagnosticSummary');

        $broadcaster->broadcastSettingsUpdated($settings);

        return $this->success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function uploadLogo(
        Request $request,
        Event $event,
        WallBroadcasterService $broadcaster,
    ): JsonResponse {
        $this->authorize('manageWall', $event);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,svg,webp', 'max:2048'],
        ]);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event->id]);
        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = "wall/events/{$event->id}/partner-logos/".Str::uuid().".{$ext}";

        if ($settings->partner_logo_path) {
            Storage::disk('public')->delete($settings->partner_logo_path);
        }

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $settings->update(['partner_logo_path' => $path]);
        $settings->refresh()->load('diagnosticSummary');

        $broadcaster->broadcastSettingsUpdated($settings);

        return $this->success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function diagnostics(
        Event $event,
        WallDiagnosticsService $diagnostics,
    ): JsonResponse {
        $this->authorize('viewWall', $event);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event->id])->load(['diagnosticSummary', 'event']);
        $diagnostics->recalculateSummary($settings, broadcast: false);

        return $this->success(
            (new WallDiagnosticsResource($diagnostics->diagnosticsPayload($settings)))->resolve(),
        );
    }

    public function simulate(
        SimulateWallRequest $request,
        Event $event,
        WallSimulationService $simulation,
    ): JsonResponse {
        $this->authorize('viewWall', $event);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event->id]);

        return $this->success(
            (new WallSimulationResource(
                $simulation->simulate($settings, $request->validated()),
            ))->resolve(),
        );
    }

    public function playerCommand(
        RunWallPlayerCommandRequest $request,
        Event $event,
        WallBroadcasterService $broadcaster,
    ): JsonResponse {
        $this->authorize('manageWall', $event);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event->id]);
        $payload = [
            'command' => $request->validated('command'),
            'reason' => $request->validated('reason'),
            'issued_at' => now()->toIso8601String(),
        ];

        $broadcaster->broadcastPlayerCommand($settings, $payload);

        return $this->success([
            'message' => 'Comando enviado aos players do wall.',
            'command' => $payload['command'],
            'issued_at' => $payload['issued_at'],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('wall.view'), 403);

        return $this->success([
            'layouts' => collect(WallLayout::enabledCases())->map(fn ($layout) => [
                'value' => $layout->value,
                'label' => $layout->label(),
                'capabilities' => $layout->capabilities(),
                'defaults' => $layout->defaults(),
            ]),
            'transitions' => collect(WallTransition::cases())->map(fn ($transition) => [
                'value' => $transition->value,
                'label' => $transition->label(),
            ]),
            'transition_modes' => collect(WallTransitionMode::cases())->map(fn ($mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ]),
            'transition_defaults' => [
                'transition_effect' => WallTransition::Fade->value,
                'transition_mode' => WallTransitionMode::Fixed->value,
            ],
            'statuses' => collect(WallStatus::cases())->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'selection_modes' => WallSelectionPreset::options(),
            'event_phases' => collect(WallEventPhase::cases())->map(fn ($phase) => [
                'value' => $phase->value,
                'label' => $phase->label(),
                'description' => $phase->description(),
            ]),
        ]);
    }
}
