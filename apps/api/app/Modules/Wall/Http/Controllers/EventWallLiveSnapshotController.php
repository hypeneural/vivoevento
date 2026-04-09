<?php

namespace App\Modules\Wall\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Http\Resources\WallLiveSnapshotResource;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Services\WallLiveSnapshotService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventWallLiveSnapshotController extends BaseController
{
    public function show(Event $event, WallLiveSnapshotService $snapshots): JsonResponse
    {
        $this->authorize('viewWall', $event);

        $settings = EventWallSetting::firstOrCreate(['event_id' => $event->id]);

        return $this->success(
            (new WallLiveSnapshotResource($snapshots->buildSnapshot($settings)))->resolve(),
        );
    }
}
