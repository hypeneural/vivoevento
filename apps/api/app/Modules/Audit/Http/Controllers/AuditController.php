<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditController extends BaseController
{
    /**
     * GET /api/v1/audit-logs
     *
     * Filterable audit log with organization scoping.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query()
            ->with('causer:id,name,email');

        // ─── Filters ──────────────────────────────────────────
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        if ($request->filled('actor_id')) {
            $query->where('causer_id', $request->input('actor_id'));
        }

        if ($request->filled('organization_id')) {
            $query->where('properties->organization_id', $request->input('organization_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->latest()->paginate(
            $request->input('per_page', 30)
        );

        // Format response
        $data = $logs->through(fn (Activity $activity) => [
            'id' => $activity->id,
            'type' => 'audit',
            'event' => $activity->event,
            'description' => $activity->description,
            'actor' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name,
            ] : null,
            'subject' => [
                'type' => class_basename($activity->subject_type ?? ''),
                'id' => $activity->subject_id,
            ],
            'context' => [
                'changes' => [
                    'old' => $activity->properties['old'] ?? null,
                    'new' => $activity->properties['attributes'] ?? null,
                ],
            ],
            'created_at' => $activity->created_at?->toISOString(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'meta' => [
                'page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'request_id' => 'req_' . \Illuminate\Support\Str::random(12),
            ],
        ]);
    }
}
