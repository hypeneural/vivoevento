<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class EventTimelineController extends BaseController
{
    /**
     * GET /api/v1/events/{eventId}/timeline
     *
     * Aggregated timeline of all activity for a specific event.
     * Combines: event changes, moderation actions, status changes,
     * QR generation, channel connections, hub publications, wall start/stop.
     */
    public function index(Request $request, int $eventId): JsonResponse
    {
        $activities = Activity::query()
            ->where(function ($q) use ($eventId) {
                $q->where(function ($sub) use ($eventId) {
                    $sub->where('subject_type', 'App\\Modules\\Events\\Models\\Event')
                        ->where('subject_id', $eventId);
                })->orWhere(function ($sub) use ($eventId) {
                    $sub->where('properties->event_id', $eventId);
                });
            })
            ->with('causer:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($request->input('limit', 50))
            ->get();

        $timeline = $activities->map(fn (Activity $activity) => [
            'id' => 'tl_' . str_pad($activity->id, 3, '0', STR_PAD_LEFT),
            'kind' => $activity->event ?? $activity->description,
            'label' => $activity->description,
            'actor_name' => $activity->causer?->name ?? 'Sistema',
            'created_at' => $activity->created_at?->toISOString(),
        ]);

        return $this->success($timeline);
    }
}
