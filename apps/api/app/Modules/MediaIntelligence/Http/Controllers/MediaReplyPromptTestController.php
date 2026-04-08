<?php

namespace App\Modules\MediaIntelligence\Http\Controllers;

use App\Modules\MediaIntelligence\Http\Requests\ListMediaReplyPromptTestsRequest;
use App\Modules\MediaIntelligence\Http\Requests\RunMediaReplyPromptTestRequest;
use App\Modules\MediaIntelligence\Http\Resources\MediaReplyTestRunResource;
use App\Modules\MediaIntelligence\Models\MediaReplyTestRun;
use App\Modules\MediaIntelligence\Services\MediaReplyPromptTestService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class MediaReplyPromptTestController extends BaseController
{
    public function index(ListMediaReplyPromptTestsRequest $request): JsonResponse
    {
        $query = MediaReplyTestRun::query()
            ->with('preset')
            ->latest();

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->integer('event_id'));
        }

        if ($request->filled('provider_key')) {
            $query->where('provider_key', (string) $request->string('provider_key'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        $runs = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(MediaReplyTestRunResource::collection($runs));
    }

    public function store(
        RunMediaReplyPromptTestRequest $request,
        MediaReplyPromptTestService $service,
    ): JsonResponse {
        $run = $service->run($request->user(), $request->validated());

        return $this->created((new MediaReplyTestRunResource($run))->resolve());
    }

    public function show(MediaReplyTestRun $teste): JsonResponse
    {
        return $this->success(new MediaReplyTestRunResource($teste->loadMissing('preset')));
    }
}
