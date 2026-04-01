<?php

namespace App\Modules\Users\Http\Controllers;

use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class UserActivityController extends BaseController
{
    /**
     * GET /api/v1/users/me/activity
     *
     * Returns the authenticated user's recent activity log.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $activities = Activity::query()
            ->where('causer_type', get_class($user))
            ->where('causer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($request->input('limit', 30))
            ->get();

        $data = $activities->map(fn (Activity $activity) => [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'subject' => [
                'type' => class_basename($activity->subject_type ?? ''),
                'id' => $activity->subject_id,
            ],
            'created_at' => $activity->created_at?->toISOString(),
        ]);

        return $this->success($data);
    }
}
