<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\CreatePublicTrialEventAction;
use App\Modules\Billing\Http\Requests\StorePublicTrialEventRequest;
use App\Modules\Billing\Http\Resources\PublicTrialEventResource;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PublicTrialEventController extends BaseController
{
    public function store(
        StorePublicTrialEventRequest $request,
        CreatePublicTrialEventAction $action,
    ): JsonResponse {
        $payload = $action->execute($request->validated());

        return $this->created(new PublicTrialEventResource($payload));
    }
}
