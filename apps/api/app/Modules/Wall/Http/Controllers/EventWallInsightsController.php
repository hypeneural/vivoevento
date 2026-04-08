<?php

namespace App\Modules\Wall\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Http\Resources\WallInsightsResource;
use App\Modules\Wall\Services\WallInsightsService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventWallInsightsController extends BaseController
{
    public function show(Event $event, WallInsightsService $insights): JsonResponse
    {
        $this->authorize('viewWall', $event);

        return $this->success(
            (new WallInsightsResource($insights->buildInsightsPayload($event)))->resolve(),
        );
    }
}
