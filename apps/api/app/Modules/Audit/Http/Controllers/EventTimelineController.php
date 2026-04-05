<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class EventTimelineController extends BaseController
{
    /**
     * GET /api/v1/events/{event}/timeline
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $activities = Activity::query()
            ->where(function ($query) use ($event) {
                $query->where(function ($subjectQuery) use ($event) {
                    $subjectQuery
                        ->where('subject_type', Event::class)
                        ->where('subject_id', $event->id);
                })->orWhere(function ($propertyQuery) use ($event) {
                    $propertyQuery->where('properties->event_id', $event->id);
                });
            })
            ->with('causer:id,name')
            ->orderByDesc('created_at')
            ->limit((int) $request->input('limit', 50))
            ->get();

        $timeline = $activities->map(fn (Activity $activity) => [
            'id' => 'tl_' . str_pad((string) $activity->id, 3, '0', STR_PAD_LEFT),
            'kind' => $activity->event ?? $activity->description,
            'label' => $activity->description,
            'actor_name' => $activity->causer?->name ?? 'Sistema',
            'created_at' => $activity->created_at?->toISOString(),
        ]);

        return $this->success($timeline);
    }
}
