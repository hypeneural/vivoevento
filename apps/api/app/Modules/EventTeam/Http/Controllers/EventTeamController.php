<?php

namespace App\Modules\EventTeam\Http\Controllers;

use App\Modules\EventTeam\Models\EventTeamMember;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventTeamController extends BaseController
{
    public function index(int $event): JsonResponse
    {
        $members = EventTeamMember::where('event_id', $event)
            ->with('user:id,name,email,avatar_path')
            ->get();

        return $this->success($members);
    }

    public function store(Request $request, int $event): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'string', 'in:manager,operator,moderator,viewer'],
        ]);

        $member = EventTeamMember::firstOrCreate(
            ['event_id' => $event, 'user_id' => $validated['user_id']],
            ['role' => $validated['role']]
        );

        return $this->success($member->load('user:id,name,email'), 201);
    }

    public function update(Request $request, int $event, EventTeamMember $member): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:manager,operator,moderator,viewer'],
        ]);

        $member->update($validated);

        return $this->success($member->fresh()->load('user:id,name,email'));
    }

    public function destroy(int $event, EventTeamMember $member): JsonResponse
    {
        $member->delete();

        return $this->success(null, 204);
    }
}
